# 📋 Subscription Lifecycle Engine - Implementation Plan

**Date:** April 5, 2026  
**Project:** Production-Grade Subscription Management API (Laravel)

---

## 🎯 Executive Summary

Build a robust, production-grade **Subscription Management System** that handles dynamic plans, multi-currency pricing, and a sophisticated lifecycle engine with automated state transitions and grace period logic.

**Key Objectives:**
- ✅ Dynamic subscription plans with flexible billing cycles
- ✅ Multi-currency support (AED, USD, EGP)
- ✅ State machine with 4 states (trialing, active, past_due, canceled)
- ✅ Grace period logic (3-day recovery window on payment failure)
- ✅ Automated daily scheduler for lifecycle transitions
- ✅ Clean architecture with separation of concerns

---

## 🏗️ High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     API Layer (Controllers)                   │
│         ├── PlanController                                    │
│         ├── SubscriptionController                            │
│         └── WebhookController                                 │
├─────────────────────────────────────────────────────────────┤
│                   Service Layer (Business Logic)              │
│  ├── PlanService                                              │
│  ├── SubscriptionService                                      │
│  ├── SubscriptionStateService                                 │
│  ├── GracePeriodService                                       │
│  └── PaymentService                                           │
├─────────────────────────────────────────────────────────────┤
│              State Machine & Domain Events                     │
│  ├── SubscriptionStateMachine (Enum-based)                   │
│  ├── SubscriptionStateEnum                                    │
│  └── Domain Events Layer                                      │
├─────────────────────────────────────────────────────────────┤
│                    Model Layer (Eloquent)                      │
│  ├── Plan, PlanBillingCycle, PlanPrice                       │
│  ├── Subscription, SubscriptionHistory                        │
│  └── FailedPayment                                            │
├─────────────────────────────────────────────────────────────┤
│                   Database Layer (MySQL)                      │
└─────────────────────────────────────────────────────────────┘
        ↓
    Laravel Scheduler (Daily Cron)
    ├── ExpireTrialsCommand
    └── ProcessGracePeriodCommand
```

---

## 📊 Database Schema Design

### 1. `plans` Table
```sql
CREATE TABLE plans (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Purpose:** Store subscription plan definitions  
**Indexes:** `id`, `name`, `is_active`

---

### 2. `plan_billing_cycles` Table
```sql
CREATE TABLE plan_billing_cycles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    plan_id BIGINT NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
    cycle_type ENUM('daily', 'weekly', 'monthly', 'quarterly', 'semi_annual', 'yearly') NOT NULL,
    duration_in_days INT NOT NULL,
    display_name VARCHAR(50),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_plan_cycle (plan_id, cycle_type)
);
```

**Purpose:** Define billing cycle options per plan  
**Indexes:** `plan_id`, `cycle_type`

---

### 3. `plan_prices` Table
```sql
CREATE TABLE plan_prices (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    plan_billing_cycle_id BIGINT NOT NULL REFERENCES plan_billing_cycles(id) ON DELETE CASCADE,
    currency VARCHAR(3) NOT NULL,
    price DECIMAL(12, 2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_cycle_currency (plan_billing_cycle_id, currency)
);
```

**Purpose:** Store multi-currency pricing  
**Indexes:** `plan_billing_cycle_id`, `currency`

---

### 4. `subscriptions` Table
```sql
CREATE TABLE subscriptions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    plan_id BIGINT NOT NULL REFERENCES plans(id),
    plan_billing_cycle_id BIGINT NOT NULL REFERENCES plan_billing_cycles(id),
    plan_price_id BIGINT NOT NULL REFERENCES plan_prices(id),
    status ENUM('trialing', 'active', 'past_due', 'canceled') NOT NULL DEFAULT 'trialing',
    
    -- Trial Information
    trial_ends_at TIMESTAMP NULL,
    
    -- Subscription Period
    started_at TIMESTAMP NOT NULL,
    ends_at TIMESTAMP NULL,
    current_period_start TIMESTAMP NOT NULL,
    current_period_end TIMESTAMP NOT NULL,
    
    -- Grace Period
    grace_period_ends_at TIMESTAMP NULL,
    
    -- Cancellation
    canceled_at TIMESTAMP NULL,
    cancellation_reason VARCHAR(255) NULL,
    
    -- Status
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_trial_ends_at (trial_ends_at),
    INDEX idx_grace_period_ends_at (grace_period_ends_at),
    INDEX idx_user_status (user_id, status)
);
```

**Purpose:** Core subscription records  
**Indexes:** Optimized for frequent queries

---

### 5. `subscription_histories` Table
```sql
CREATE TABLE subscription_histories (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    subscription_id BIGINT NOT NULL REFERENCES subscriptions(id) ON DELETE CASCADE,
    previous_status ENUM('trialing', 'active', 'past_due', 'canceled') NULL,
    new_status ENUM('trialing', 'active', 'past_due', 'canceled') NOT NULL,
    reason VARCHAR(255) NOT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_created_at (created_at)
);
```

**Purpose:** Audit trail for compliance  
**Indexes:** Optimized for historical queries

---

### 6. `failed_payments` Table
```sql
CREATE TABLE failed_payments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    subscription_id BIGINT NOT NULL REFERENCES subscriptions(id) ON DELETE CASCADE,
    amount DECIMAL(12, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    failure_reason VARCHAR(255) NOT NULL,
    provider_error_code VARCHAR(50),
    provider_error_message TEXT,
    failed_at TIMESTAMP NOT NULL,
    recovered BOOLEAN DEFAULT FALSE,
    recovered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_failed_at (failed_at),
    INDEX idx_recovered (recovered)
);
```

**Purpose:** Track payment failures for analytics  
**Indexes:** Optimized for payment analysis

---

## 🔄 State Machine Design

### Subscription States

```
┌──────────────────────────────────────────────────────────────┐
│                   TRIALING State                              │
│  • User is in trial period                                    │
│  • Access to features enabled                                 │
│  • trial_ends_at is set                                       │
│  • Automatic transition after trial_ends_at                   │
└──────────────────────────────────────────────────────────────┘
         ↙                                    ↘
    activate()                            cancel()
         ↓                                    ↓
┌──────────────────────────────────────────────────────────────┐
│                    ACTIVE State                               │
│  • Subscription is active & valid                             │
│  • User has paid or is in trial                               │
│  • Access to all features                                     │
│  • Next billing date: current_period_end                      │
└──────────────────────────────────────────────────────────────┘
         ↙                                    ↘
 markPastDue()                           cancel()
     (payment fails)                         ↓
         ↓                         ┌─────────────────────┐
┌──────────────────────────────────────────────────────────────┐
│                   PAST_DUE State                              │
│  • Payment failed                                             │
│  • Grace period active (3 days)                               │
│  • Access STILL ENABLED (key feature!)                        │
│  • grace_period_ends_at: now + 3 days                         │
│  • Awaiting payment retry or recovery                         │
└──────────────────────────────────────────────────────────────┘
         ↙                                    ↘
    recover()                        cancel()
    (payment OK)              (grace period expired
         ↓                      or user chooses)
   ACTIVE ────────────────┐        ↓
                          │  ┌──────────────────────────────────────────┐
                          │  │                CANCELED State             │
                          │  │  • Subscription is terminated             │
                          │  │  • canceled_at timestamp set              │
                          │  │  • Access revoked immediately             │
                          │  │  • Terminal state (no outbound edges)     │
                          │  │  • Reason stored (grace expired/user)     │
                          └─→└──────────────────────────────────────────┘
```

### State Transition Rules

| From → To | Allowed | Trigger |
|-----------|---------|---------|
| **trialing → active** | ✅ | Trial expires naturally |
| **trialing → canceled** | ✅ | User cancels during trial |
| **active → past_due** | ✅ | Payment fails |
| **active → canceled** | ✅ | User initiates cancellation |
| **past_due → active** | ✅ | Payment recovered within grace period |
| **past_due → canceled** | ✅ | Grace period expires OR user cancels |
| **canceled → *any*** | ❌ | Terminal state - no transitions |
| **Any invalid transition** | ❌ | Throws exception |

---

## ⏰ Grace Period & Scheduler Logic

### Grace Period Flow

```
[Payment Request]
       ↓
   [FAILS]
       ↓
[Move to past_due]
[grace_period_ends_at = NOW() + 3 DAYS]
[Keep access ENABLED]
[Send email to user]
       ↓
┌──Wait for user action──┐
│                        │
├─[Payment Recovered]    │
│        ↓               │
│   [RETRY SUCCESS]      │
│        ↓               │
│ [activate()]           │
│ [Reset period]         │
│ [Send confirmation]    │
│                        │
├─[3 Days Elapsed]       │
│        ↓               │
│  [Scheduler runs]      │
│        ↓               │
│ [cancel()]             │
│ [Remove access]        │
│ [Send final notice]    │
│                        │
└────────────────────────┘
```

### Scheduler Commands (Daily)

#### 1. `ExpireTrialsCommand`
**Runs:** Daily at 00:01 UTC

**Logic:**
```
SELECT subscriptions
WHERE status = 'trialing'
  AND trial_ends_at <= NOW()
  AND updated_at < NOW() - 1 HOUR

FOR EACH subscription:
  - Call $subscription->activate()
  - Log to subscription_histories
  - Send "Trial ended, billing started" email
```

**Idempotency Check:**
- Only process if last update > 1 hour ago
- Or use processed flag in metadata

**Edge Cases:**
- User cancels trial → skip activation
- Subscription already active → skip

---

#### 2. `ProcessGracePeriodCommand`
**Runs:** Daily at 01:00 UTC

**Logic:**
```
SELECT subscriptions
WHERE status = 'past_due'
  AND grace_period_ends_at <= NOW()
  AND updated_at < NOW() - 1 HOUR

FOR EACH subscription:
  - Call $subscription->cancel()
  - Set cancellation_reason = 'grace_period_expired'
  - Log to subscription_histories
  - Revoke access
  - Send "Subscription canceled" email
```

**Idempotency Check:**
- Check if already canceled (updated_at, status)
- Use transaction to prevent race conditions

**Edge Cases:**
- Payment recovered during grace → skip (status != past_due)
- User manually canceled → skip (status changed)

---

## 🔐 Service Layer Architecture

### 1. `PlanService`

**Responsibilities:**
- Create and manage subscription plans
- Handle billing cycle configuration
- Manage multi-currency pricing

**Key Methods:**
```php
public function createPlan(array $data): Plan
public function addBillingCycle(Plan $plan, string $type, int $durationDays): PlanBillingCycle
public function addPricing(PlanBillingCycle $cycle, string $currency, float $price): PlanPrice
public function getPricingByCurrencyAndCycle(Plan $plan, string $currency, string $billingCycle): ?PlanPrice
public function listActivePlans(int $page, int $perPage): Paginator
public function activatePlan(Plan $plan): Plan
public function deactivatePlan(Plan $plan): Plan
```

---

### 2. `SubscriptionService`

**Responsibilities:**
- Create new subscriptions
- Manage subscription lifecycle operations
- Calculate billing periods

**Key Methods:**
```php
public function createSubscription(User $user, Plan $plan, PlanBillingCycle $cycle, string $currency, bool $withTrial = false, int $trialDays = 14): Subscription
public function getUserSubscriptions(User $user, int $page = 1): Paginator
public function getSubscriptionDetails(Subscription $subscription): array
public function calculateNextBillingDate(Subscription $subscription): Carbon
public function changeSubscriptionPlan(Subscription $subscription, Plan $newPlan, PlanBillingCycle $newCycle): Subscription
public function changeSubscriptionCycle(Subscription $subscription, PlanBillingCycle $newCycle): Subscription
public function listSubscriptionHistory(Subscription $subscription): Collection
```

---

### 3. `SubscriptionStateService`

**Responsibilities:**
- Manage state transitions
- Validate transitions
- Record state changes

**Key Methods:**
```php
public function activate(Subscription $subscription, string $reason = 'Trial ended'): Subscription
public function markPastDue(Subscription $subscription, string $reason): Subscription
public function recover(Subscription $subscription): Subscription
public function cancel(Subscription $subscription, string $reason = 'User requested'): Subscription
public function canTransitionTo(Subscription $subscription, SubscriptionStateEnum $newState): bool
public function getValidTransitions(Subscription $subscription): array
private function recordTransition(Subscription $subscription, SubscriptionStateEnum $newState, string $reason): SubscriptionHistory
```

**State Machine Enum:**
```php
enum SubscriptionStateEnum: string {
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    
    public function validTransitionsTo(): array {
        return match($this) {
            self::TRIALING => [self::ACTIVE, self::CANCELED],
            self::ACTIVE => [self::PAST_DUE, self::CANCELED],
            self::PAST_DUE => [self::ACTIVE, self::CANCELED],
            self::CANCELED => [],
        };
    }
}
```

---

### 4. `GracePeriodService`

**Responsibilities:**
- Manage grace period logic
- Check grace period expiry
- Process expired grace periods

**Key Methods:**
```php
public function startGracePeriod(Subscription $subscription, int $daysUntilExpiry = 3): Subscription
public function isInGracePeriod(Subscription $subscription): bool
public function getGraceRemainingDays(Subscription $subscription): int
public function processExpiredGracePeriods(): Collection
public function endGracePeriod(Subscription $subscription): Subscription
```

---

### 5. `PaymentService`

**Responsibilities:**
- Handle payment webhooks
- Track failed payments
- Trigger state transitions

**Key Methods:**
```php
public function handlePaymentSuccess(Subscription $subscription): Subscription
public function handlePaymentFailure(Subscription $subscription, string $errorReason, ?string $errorCode = null): Subscription
public function recordFailedPayment(Subscription $subscription, float $amount, string $currency, string $reason): FailedPayment
public function retryFailedPayment(FailedPayment $failedPayment): bool
```

---

## 📡 API Endpoints

### Plans Management

```
GET     /api/v1/plans
POST    /api/v1/plans
GET     /api/v1/plans/{id}
PUT     /api/v1/plans/{id}
DELETE  /api/v1/plans/{id}

POST    /api/v1/plans/{id}/billing-cycles
POST    /api/v1/plans/{id}/pricing

GET     /api/v1/plans/{id}/pricing?currency=USD&cycle=monthly
```

### Subscriptions

```
GET     /api/v1/subscriptions              [List user subscriptions]
POST    /api/v1/subscriptions              [Create subscription]
GET     /api/v1/subscriptions/{id}         [Get subscription details]
PUT     /api/v1/subscriptions/{id}         [Update plan/cycle]
DELETE  /api/v1/subscriptions/{id}         [Cancel subscription]

POST    /api/v1/subscriptions/{id}/cancel           [Initiate cancellation]
GET     /api/v1/subscriptions/{id}/history          [Get state history]
POST    /api/v1/subscriptions/{id}/retry-payment    [Retry failed payment]
PUT     /api/v1/subscriptions/{id}/billing-cycle    [Change billing cycle]
```

### Webhooks

```
POST    /api/v1/webhooks/payment-success       [Handle payment success]
POST    /api/v1/webhooks/payment-failed        [Handle payment failure]
POST    /api/v1/webhooks/payment-recovered     [Handle recovery after failure]
```

---

## 🧪 Request/Response Examples

### Create Plan

**Request:**
```json
POST /api/v1/plans
{
  "name": "Professional",
  "description": "For growing teams",
  "features": ["Advanced analytics", "API access", "Priority support"]
}
```

**Response:**
```json
{
  "id": 1,
  "name": "Professional",
  "description": "For growing teams",
  "features": ["Advanced analytics", "API access", "Priority support"],
  "created_at": "2026-04-05T10:00:00Z"
}
```

---

### Add Pricing

**Request:**
```json
POST /api/v1/plans/1/billing-cycles
{
  "cycle_type": "monthly",
  "duration_in_days": 30,
  "display_name": "Monthly"
}

POST /api/v1/plans/1/pricing
{
  "plan_billing_cycle_id": 1,
  "currency": "USD",
  "price": 99.99
}
```

---

### Create Subscription

**Request:**
```json
POST /api/v1/subscriptions
{
  "plan_id": 1,
  "plan_billing_cycle_id": 1,
  "currency": "USD",
  "trial_period_days": 14
}
```

**Response:**
```json
{
  "id": 5,
  "user_id": 123,
  "plan_id": 1,
  "plan_name": "Professional",
  "status": "trialing",
  "trial_ends_at": "2026-04-19T10:00:00Z",
  "started_at": "2026-04-05T10:00:00Z",
  "current_period_start": "2026-04-05T10:00:00Z",
  "current_period_end": "2026-05-05T10:00:00Z",
  "price": {
    "amount": 99.99,
    "currency": "USD",
    "billing_cycle": "monthly"
  },
  "created_at": "2026-04-05T10:00:00Z"
}
```

---

### Payment Failure Webhook

**Request:**
```json
POST /api/v1/webhooks/payment-failed
{
  "subscription_id": 5,
  "amount": 99.99,
  "currency": "USD",
  "reason": "card_declined",
  "error_code": "card_declined",
  "error_message": "Your card was declined."
}
```

**System Actions:**
1. Update subscription status to `past_due`
2. Set `grace_period_ends_at` = now + 3 days
3. Keep access enabled
4. Send email to user with retry link
5. Record in `failed_payments` table

---

## 🎯 Edge Cases Handled

### 1. **Payment Recovery During Grace Period**
- User's payment succeeds before grace period expires
- Call `PaymentService::handlePaymentSuccess()`
- Transition back to `active`
- Reset `current_period_end`
- Email confirmation to user

### 2. **Timezone Consistency**
- All timestamps stored in UTC
- Use Carbon with timezone awareness
- Convert to user timezone only on display
- Example: `Carbon::now('UTC')`

### 3. **Idempotent Cron Execution**
- Check `updated_at` to avoid re-processing within same hour
- Or use unique constraint on processed flag
- Use database transactions to prevent race conditions
- Log all cron executions with result status

### 4. **Concurrent State Transitions**
- Use database transactions
- Lock subscription row during update: `lockForUpdate()`
- Prevent race condition on multiple webhook sets

### 5. **Trial Without Billing Cycle**
- Support free trials with no charge
- `trial_ends_at` set, `price.amount` = 0
- Auto-transition to canceled after trial (not active)
- Configurable per plan

### 6. **User Cancels During Trial**
- Transition from `trialing` → `canceled` directly
- Don't activate first
- Set `cancellation_reason = 'user_canceled_during_trial'`

### 7. **Currency Mismatch**
- Validate plan_price currency matches request
- Throw exception if currency not supported
- Display available currencies in response

### 8. **Downgrade/Upgrade Mid-Cycle**
- Allow plan change anytime
- Prorate charges (if needed)
- New period starts immediately or at cycle end (configurable)

### 9. **Duplicate Webhook Events**
- Use idempotency key pattern
- Store processed webhook IDs
- Return 200 OK for duplicate events

### 10. **Database Constraints**
- Foreign keys with CASCADE for data integrity
- Unique constraints on plan/cycle/currency
- Indexes on frequently queried columns

---

## 🛠️ Implementation Phases

### **Phase 1: Foundation** (~2-3 hours)
- [ ] Create Laravel project structure
- [ ] Database migrations (all 6 tables)
- [ ] Eloquent models with relationships
- [ ] Enums for states
- [ ] Model scopes for common queries

### **Phase 2: Core Services** (~3-4 hours)
- [ ] `PlanService` - Plan management
- [ ] `SubscriptionService` - Subscription operations
- [ ] `SubscriptionStateService` - State machine
- [ ] `GracePeriodService` - Grace period logic
- [ ] `PaymentService` - Payment handling

### **Phase 3: API Layer** (~2-3 hours)
- [ ] Controllers (thin, delegating to services)
- [ ] Route definitions (RESTful)
- [ ] Request validation (FormRequest)
- [ ] Response formatting (Resources)
- [ ] Error handling (custom exceptions)

### **Phase 4: Automation** (~1-2 hours)
- [ ] `ExpireTrialsCommand` scheduled job
- [ ] `ProcessGracePeriodCommand` scheduled job
- [ ] Register in `Kernel.php`
- [ ] Webhook handlers for payment events

### **Phase 5: Documentation & Setup** (~1-2 hours)
- [ ] Postman collection (JSON)
- [ ] README with architecture explanation
- [ ] Tradeoffs & decisions document
- [ ] Edge cases & examples
- [ ] Setup instructions

---

## 📊 Database Relationships (ERD)

```
┌─────────────────┐
│    users        │
│  (Laravel)      │
└────────┬────────┘
         │ 1:N
         │
┌────────▼────────────────┐         ┌──────────────────┐
│   subscriptions         │─────────→│  subscription    │
│  (core table)           │ 1:N      │  histories       │
└────────┬────────────────┘         └──────────────────┘
         │ N:1
         │
    ┌────┴─────────────────┐
    │                      │
┌───▼──────────┐    ┌─────▼────────────┐
│    plans     │    │failed_payments   │
└───┬──────────┘    └──────────────────┘
    │ 1:N
    │
┌───▼───────────────────┐
│ plan_billing_cycles   │
└───┬───────────────────┘
    │ 1:N
    │
┌───▼──────────────────┐
│   plan_prices        │
│ (multi-currency)     │
└──────────────────────┘
```

---

## 🚀 Key Design Principles

| Principle | Implementation |
|-----------|-----------------|
| **Single Responsibility** | Each service handles one domain |
| **State Machine Pattern** | Enum with validation for transitions |
| **Audit Trail** | Every state change recorded |
| **Idempotency** | Safe re-execution of cron jobs |
| **Timezone Safety** | All UTC, convert on display |
| **Data Normalization** | No price/cycle hardcoding |
| **Clean Controllers** | Thin controllers, fat services |
| **Testability** | Services are testable units |
| **Scalability** | Proper indexes, normalized schema |
| **Error Handling** | Custom exceptions, clear messages |

---

## 📝 Deliverables Checklist

- [ ] **Project Structure** - Organized with clear separation
- [ ] **Database Migrations** - All 6 tables with relationships
- [ ] **Models** - With scopes, relationships, casts
- [ ] **Enums** - State machine with validation
- [ ] **Services** (5) - Plan, Subscription, State, GracePeriod, Payment
- [ ] **Controllers** (3) - Plan, Subscription, Webhook
- [ ] **Routes** - RESTful endpoints
- [ ] **Validation** - FormRequests for all endpoints
- [ ] **Resources** - API response formatting
- [ ] **Scheduled Commands** (2) - Trial expiry, grace period
- [ ] **Webhook Handlers** - Payment event processing
- [ ] **Postman Collection** - JSON with examples
- [ ] **README** - Architecture, decisions, edge cases, setup
- [ ] **Error Handling** - Custom exceptions
- [ ] **Timestamps** - Proper Carbon usage

---

## ⚠️ Important Notes

✅ **DO:**
- Place all business logic in services
- Use enums for state management
- Validate state transitions
- Log all important state changes
- Make scheduler jobs idempotent
- Use transactions for multi-step operations
- Include timezone awareness
- Test edge cases thoroughly

❌ **DON'T:**
- Hardcode prices or billing cycles
- Put business logic in controllers
- Store state as plain string
- Skip state transition validation
- Forget about grace periods
- Use cronjobs without idempotency
- Ignore timezone issues
- Leave webhooks without duplicate handling

---

## 🎬 Next Steps

1. **Create project structure**
2. **Set up database schema with migrations**
3. **Build Eloquent models**
4. **Implement service layer**
5. **Create API controllers and routes**
6. **Add scheduled commands**
7. **Generate Postman collection**
8. **Write comprehensive README**
9. **Test edge cases**
10. **Deploy to production**

---

**Status:** Ready for Implementation  
**Last Updated:** April 5, 2026

