# 📊 Subscription Lifecycle Engine - Comprehensive Project Analysis

**Project**: Subscription Lifecycle Engine  
**Framework**: Laravel 11  
**PHP Version**: 8.2+  
**Database**: MySQL 8.0+  
**Authentication**: Laravel Sanctum  
**Date**: April 6, 2026

---

## 🎯 Executive Summary

The **Subscription Lifecycle Engine** is a production-grade subscription management system built with Laravel 11. It provides complete subscription management with:

- **Dynamic Plans**: Flexible subscription plans with multiple billing cycles
- **Multi-Currency Support**: AED, USD, EGP (and extensible)
- **State Machine**: 4 states with validated transitions (trialing → active → past_due → canceled)
- **Grace Period Logic**: 3-day recovery window for failed payments
- **Automated Lifecycle**: Daily cron jobs for trial expiry and grace period processing
- **Payment Webhooks**: Integration points for payment provider events
- **Complete Audit Trail**: Full subscription history tracking
- **Clean Architecture**: Service layer pattern with separation of concerns

---

## 📂 Project Structure

### Root Directory
```
Subscription Lifecycle Engine/
├── app/
│   ├── Console/Commands/          # Scheduled commands for cron jobs
│   ├── Enums/                     # SubscriptionState enum
│   ├── Http/
│   │   ├── Controllers/           # API controllers
│   │   ├── Requests/              # Form request validation
│   │   └── Resources/             # Response formatters
│   ├── Models/                    # Eloquent models
│   ├── Providers/                 # Service providers
│   ├── Services/                  # Business logic layer
│   ├── Traits/                    # Shared traits (ApiResponse)
│   └── Exceptions/                # Exception handling
├── bootstrap/                     # Laravel boot files
├── config/                        # Configuration files
├── database/
│   ├── migrations/                # Schema migrations
│   ├── factories/                 # Model factories for testing
│   └── seeders/                   # Database seeders
├── public/                        # Public assets
├── resources/                     # Frontend assets
├── routes/                        # Route definitions
├── storage/                       # Logs, cache, uploads
├── tests/                         # Unit & Feature tests
├── vendor/                        # Composer dependencies
├── .env.example                   # Environment template
├── composer.json                  # PHP dependencies
├── package.json                   # Node dependencies
└── README.md, PLAN.md, API-ROUTES.md, SETUP.md
```

---

## 🗄️ Database Schema & Relationships

### 1. **Users Table** (Laravel Default)
```sql
Columns:
- id (BIGINT PRIMARY KEY)
- name (VARCHAR)
- email (VARCHAR UNIQUE)
- password (VARCHAR HASHED)
- email_verified_at (TIMESTAMP NULL)
- remember_token (VARCHAR)
- created_at, updated_at (TIMESTAMPS)
```
**Purpose**: Store user accounts  
**Relationship**: One-to-Many with `subscriptions`

---

### 2. **Plans Table**
```sql
CREATE TABLE plans (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE,
    description TEXT,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
**Purpose**: Define subscription plan products  
**Features**:
- Store as JSON array (e.g., ["Feature 1", "Feature 2"])
- Reusable across multiple subscriptions
- Can be activated/deactivated

**Relationships**:
- One-to-Many: `billingCycles`
- One-to-Many: `subscriptions`

**Indexes**: `id`, `name`, `is_active`

---

### 3. **Plan Billing Cycles Table**
```sql
CREATE TABLE plan_billing_cycles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    plan_id BIGINT NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
    cycle_type ENUM('daily', 'weekly', 'monthly', 'quarterly', 'semi_annual', 'yearly'),
    duration_in_days INT,
    display_name VARCHAR(50),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_plan_cycle (plan_id, cycle_type)
);
```
**Purpose**: Define billing cycle options for each plan  
**Supported Cycles**:
- `daily`: 1 day
- `weekly`: 7 days
- `monthly`: 30-31 days (based on duration_in_days)
- `quarterly`: 90 days
- `semi_annual`: 180 days
- `yearly`: 365 days

**Relationships**:
- Many-to-One: `plan`
- One-to-Many: `prices`
- One-to-Many: `subscriptions`

**Indexes**: `plan_id`, `cycle_type`

---

### 4. **Plan Prices Table**
```sql
CREATE TABLE plan_prices (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    plan_billing_cycle_id BIGINT NOT NULL REFERENCES plan_billing_cycles(id) ON DELETE CASCADE,
    currency VARCHAR(3),
    price DECIMAL(12, 2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_cycle_currency (plan_billing_cycle_id, currency)
);
```
**Purpose**: Store multi-currency pricing  
**Supported Currencies**: AED, USD, EGP (extensible)  
**Price Format**: DECIMAL(12, 2) for precision  
**Example**:
```
Plan: "Pro" → Monthly ($29.99 USD, 149 AED, 500 EGP)
```

**Relationships**:
- Many-to-One: `billingCycle`
- One-to-Many: `subscriptions`

**Indexes**: `plan_billing_cycle_id`, `currency`

---

### 5. **Subscriptions Table** (Core)
```sql
CREATE TABLE subscriptions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    plan_id BIGINT NOT NULL REFERENCES plans(id),
    plan_billing_cycle_id BIGINT NOT NULL REFERENCES plan_billing_cycles(id),
    plan_price_id BIGINT NOT NULL REFERENCES plan_prices(id),
    
    -- Status (State Machine)
    status ENUM('trialing', 'active', 'past_due', 'canceled'),
    
    -- Trial Information
    trial_ends_at TIMESTAMP NULL,
    
    -- Subscription Period
    started_at TIMESTAMP,
    ends_at TIMESTAMP NULL,
    current_period_start TIMESTAMP,
    current_period_end TIMESTAMP,
    
    -- Grace Period (for past_due)
    grace_period_ends_at TIMESTAMP NULL,
    
    -- Cancellation
    canceled_at TIMESTAMP NULL,
    cancellation_reason VARCHAR(255),
    
    -- Soft Delete Support
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_trial_ends_at (trial_ends_at),
    INDEX idx_grace_period_ends_at (grace_period_ends_at)
);
```

**Purpose**: Track individual user subscriptions  
**Key Fields**:
- Denormalized plan, cycle, and price IDs for query efficiency
- Soft deletes for historical records
- Timestamps for lifecycle tracking

**Relationships**:
- Many-to-One: `user`
- Many-to-One: `plan`
- Many-to-One: `billingCycle`
- Many-to-One: `price`
- One-to-Many: `histories`
- One-to-Many: `failedPayments`

---

### 6. **Subscription Histories Table** (Audit Trail)
```sql
CREATE TABLE subscription_histories (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    subscription_id BIGINT NOT NULL REFERENCES subscriptions(id) ON DELETE CASCADE,
    previous_status ENUM('trialing', 'active', 'past_due', 'canceled') NULL,
    new_status ENUM('trialing', 'active', 'past_due', 'canceled'),
    reason VARCHAR(255),
    metadata JSON,
    created_at TIMESTAMP,
    
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_created_at (created_at)
);
```

**Purpose**: Complete audit trail of all state transitions  
**Example Transitions**:
```
1. null → trialing: "Subscription created with trial"
2. trialing → active: "Trial ended"
3. active → past_due: "Payment failed: Card declined"
4. past_due → active: "Payment recovered during grace period"
5. active → canceled: "User requested cancellation"
```

**Metadata Contains**:
```json
{
  "plan_name": "Pro",
  "billing_cycle": "monthly",
  "currency": "USD",
  "price": 29.99,
  "trial_days": 14,
  "transition_type": "trial_activation",
  "automated": true,
  "timestamp": "2026-04-06T10:30:00Z"
}
```

---

### 7. **Failed Payments Table**
```sql
CREATE TABLE failed_payments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    subscription_id BIGINT NOT NULL REFERENCES subscriptions(id) ON DELETE CASCADE,
    amount DECIMAL(12, 2),
    currency VARCHAR(3),
    failure_reason VARCHAR(255),
    provider_error_code VARCHAR(50),
    provider_error_message TEXT,
    failed_at TIMESTAMP,
    recovered BOOLEAN DEFAULT FALSE,
    recovered_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_failed_at (failed_at),
    INDEX idx_recovered (recovered)
);
```

**Purpose**: Track payment failures for diagnostics and recovery  
**Example**:
```
Card declined → Grace period started → Payment retried → Recovered
```

**Relationships**:
- Many-to-One: `subscription`

---

## 🔄 Subscription State Machine

### Valid State Transitions

```
TRIALING (Initial State - Trial Period)
├─→ ACTIVE (Trial ended or no trial)
└─→ CANCELED (User cancels during trial)

ACTIVE (Subscription Active)
├─→ PAST_DUE (Payment failed)
└─→ CANCELED (User cancels)

PAST_DUE (Payment Failed - Grace Period Active)
├─→ ACTIVE (Payment recovered within grace period)
└─→ CANCELED (Grace period expired OR user cancels)

CANCELED (Terminal State - No outbound transitions)
```

### State Descriptions

| State | Duration | Description | Actions |
|-------|----------|-------------|---------|
| **TRIALING** | Configurable | Subscription is in trial period | Can access full features. Can cancel or wait for trial to end. |
| **ACTIVE** | Current period | Subscription is active and valid | Can use features. Can cancel. Can change plan. |
| **PAST_DUE** | 3 days (grace period) | Payment failed, in recovery window | Can retry payment. Can access features. Can cancel. |
| **CANCELED** | ∞ | Subscription has been canceled | No features. Terminal state. |

---

## 👥 Models & Relationships

### User Model
```php
namespace App\Models;

class User extends Authenticatable {
    // Traits
    use HasApiTokens,  // Laravel Sanctum
         HasFactory,
         Notifiable;

    // Relationships
    public function subscriptions(): HasMany {
        return $this->hasMany(Subscription::class);
    }

    // Mass Assignable
    protected $fillable = ['name', 'email', 'password'];
}
```

---

### Plan Model
```php
namespace App\Models;

class Plan extends Model {
    // Cast features as array
    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function billingCycles(): HasMany
    public function subscriptions(): HasMany

    // Scopes
    public function scopeActive($query)
}
```

---

### Subscription Model
```php
namespace App\Models;

class Subscription extends Model {
    // Soft delete for history preservation
    use SoftDeletes;

    // Status is cast to SubscriptionState enum
    protected $casts = [
        'status' => SubscriptionState::class,
        'trial_ends_at' => 'datetime',
        'started_at' => 'datetime',
        // ... all date fields
    ];

    // Helper Methods
    public function isTrialing(): bool
    public function isActive(): bool
    public function isPastDue(): bool
    public function isCanceled(): bool
    public function isInGracePeriod(): bool
    public function getGracePeriodRemainingDays(): int

    // Relationships
    public function user(): BelongsTo
    public function plan(): BelongsTo
    public function billingCycle(): BelongsTo
    public function price(): BelongsTo
    public function histories(): HasMany
    public function failedPayments(): HasMany
}
```

---

### Enum: SubscriptionState
```php
namespace App\Enums;

enum SubscriptionState: string {
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';

    // Methods
    public function validTransitionsTo(): array
    public function canTransitionTo(self $newState): bool
    public function label(): string
    public function description(): string
    public function isTerminal(): bool
    public static function options(): array
}
```

---

## 🎮 Service Layer

### 1. **PlanService**
**Purpose**: Plan management and pricing logic  
**Key Methods**:
- `createPlan(array $data): Plan` - Create new plan
- `addBillingCycle(Plan $plan, string $cycleType, int $durationInDays): PlanBillingCycle`
- `addPricing(PlanBillingCycle $cycle, string $currency, float $price): PlanPrice`
- `getPricingByCurrencyAndCycle(Plan $plan, string $currency, string $billingCycle): ?PlanPrice`
- `listActivePlans(int $page, int $perPage): Paginator`
- `getPlanDetails(Plan $plan): Plan`
- `activatePlan(Plan $plan): Plan`
- `deactivatePlan(Plan $plan): Plan`
- `updatePlan(Plan $plan, array $data): Plan`

**Example Usage**:
```php
$plan = $planService->createPlan([
    'name' => 'Pro',
    'description' => 'Professional plan',
    'features' => ['Feature 1', 'Feature 2'],
    'is_active' => true
]);

$cycle = $planService->addBillingCycle($plan, 'monthly', 30);
$price = $planService->addPricing($cycle, 'USD', 29.99);
```

---

### 2. **SubscriptionService**
**Purpose**: Subscription lifecycle management  
**Key Methods**:
- `createSubscription(User $user, Plan $plan, PlanBillingCycle $cycle, string $currency, bool $withTrial, int $trialDays): Subscription`
- `getUserSubscriptions(User $user, int $page, int $perPage): Paginator`
- `getSubscriptionDetails(Subscription $subscription): array`
- `calculateNextBillingDate(Subscription $subscription): Carbon`
- `changeSubscriptionPlan(Subscription $subscription, Plan $newPlan, PlanBillingCycle $newCycle, string $currency): Subscription`
- `extendSubscriptionPeriod(Subscription $subscription): Subscription`

**Workflows**:
```php
// Create with trial
$subscription = $subscriptionService->createSubscription(
    user: $user,
    plan: $plan,
    cycle: $cycle,
    currency: 'USD',
    withTrial: true,
    trialDays: 14
);

// Get detailed info
$details = $subscriptionService->getSubscriptionDetails($subscription);
// Returns: id, user_id, plan, billing_cycle, pricing, status, dates, history, etc.
```

---

### 3. **SubscriptionStateService**
**Purpose**: State machine transitions and state management  
**Key Methods**:
- `activate(Subscription $subscription, string $reason): Subscription`
- `markPastDue(Subscription $subscription, string $reason): Subscription`
- `recover(Subscription $subscription): Subscription`
- `cancel(Subscription $subscription, string $reason): Subscription`
- `canTransitionTo(Subscription $subscription, SubscriptionState $newState): bool`
- `getValidTransitions(Subscription $subscription): array`
- `forceTransitionTo(Subscription $subscription, SubscriptionState $newState, string $reason): Subscription`

**State Transition Flow**:
```php
// Automatic: Trial ended
$stateService->activate($subscription, 'Trial ended');
// Transitions: trialing → active

// Automatic: Payment failed
$stateService->markPastDue($subscription, 'Payment failed: Card declined');
// Transitions: active → past_due (with grace period)

// Automatic: Payment recovered
$stateService->recover($subscription);
// Transitions: past_due → active

// User: Cancel subscription
$stateService->cancel($subscription, 'User requested cancellation');
// Transitions: active → canceled (or any valid state → canceled)
```

**Transaction Safety**:
- All operations wrapped in database transactions
- Automatic history recording
- Metadata tracking

---

### 4. **GracePeriodService**
**Purpose**: Grace period management (3-day recovery window)  
**Key Methods**:
- `startGracePeriod(Subscription $subscription, int $daysUntilExpiry = 3): Subscription`
- `isInGracePeriod(Subscription $subscription): bool`
- `getGraceRemainingDays(Subscription $subscription): int`
- `getGraceRemainingHours(Subscription $subscription): int`
- `hasGracePeriodExpired(Subscription $subscription): bool`
- `endGracePeriod(Subscription $subscription): Subscription`
- `extendGracePeriod(Subscription $subscription, int $additionalDays): Subscription`
- `processExpiredGracePeriods(): Collection` - **Scheduled task**

**Grace Period Lifecycle**:
```
1. Payment fails → PAST_DUE status + grace_period_ends_at = now + 3 days
2. User has 3 days to retry payment
3. Payment succeeds → ACTIVE status + grace_period cleared
4. Grace period expires → CANCELED status
```

**Scheduler Integration** (run daily):
```bash
# Cancels subscriptions where grace_period_ends_at has passed
php artisan schedule:run
```

---

### 5. **PaymentService**
**Purpose**: Payment event handling and recovery  
**Key Methods**:
- `handlePaymentSuccess(Subscription $subscription): Subscription`
- `handlePaymentFailure(Subscription $subscription, string $errorReason, ?string $errorCode, ?string $errorMessage): Subscription`
- `recordFailedPayment(...): FailedPayment`
- `retryFailedPayment(FailedPayment $failedPayment): bool`

**Webhook Processing**:
```php
// Payment success webhook
$subscription = $paymentService->handlePaymentSuccess($subscription);
// If past_due: transitions to active with grace period cleared
// If active: extends current period
// If trialing: logs warning

// Payment failure webhook
$subscription = $paymentService->handlePaymentFailure(
    subscription: $subscription,
    errorReason: 'Card declined',
    errorCode: 'card_declined',
    errorMessage: 'Your card was declined'
);
// If active: transitions to past_due with grace period started
// Records failed payment for audit trail
```

---

## 🎯 API Endpoints

### Authentication

#### 1. Register User
```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!"
}
```

**Validation**:
- `name`: required|string|max:255
- `email`: required|email|unique:users|max:255
- `password`: required|string|min:8|confirmed

**Response (201 Created)**:
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "created_at": "2026-04-06T10:00:00Z"
    },
    "token": "1|abc123xyz..."
  }
}
```

---

#### 2. Login User
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "SecurePassword123!"
}
```

**Validation**:
- `email`: required|email
- `password`: required|string

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "token": "1|abc123xyz..."
  }
}
```

---

#### 3. Logout
```http
POST /api/auth/logout
Authorization: Bearer {token}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "Logout successful"
}
```

---

#### 4. Get Tokens
```http
GET /api/auth/tokens
Authorization: Bearer {token}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "api-token",
      "last_used_at": "2026-04-06T10:00:00Z"
    }
  ]
}
```

---

### Plans

#### 1. List Active Plans
```http
GET /api/plans?page=1&per_page=15
Authorization: Bearer {token}
```

**Query Parameters**:
- `page`: integer|min:1 (default: 1)
- `per_page`: integer|min:1|max:100 (default: 15)

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "Plans retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Pro",
      "description": "Professional plan",
      "features": ["Feature 1", "Feature 2"],
      "is_active": true,
      "billing_cycles": [
        {
          "id": 1,
          "cycle_type": "monthly",
          "display_name": "Monthly",
          "duration_in_days": 30,
          "prices": [
            {
              "id": 1,
              "currency": "USD",
              "price": "29.99",
              "is_active": true
            }
          ]
        }
      ],
      "created_at": "2026-04-05T10:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 5,
    "last_page": 1,
    "from": 1,
    "to": 5
  }
}
```

---

#### 2. Create Plan
```http
POST /api/plans/create
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Pro",
  "description": "Professional plan with advanced features",
  "features": ["Feature 1", "Feature 2", "Feature 3"],
  "is_active": true
}
```

**Validation**:
- `name`: required|string|unique:plans|max:255
- `description`: nullable|string|max:1000
- `features`: nullable|array
- `features.*`: string|max:255

**Response (201 Created)**:
```json
{
  "success": true,
  "message": "Plan created successfully",
  "data": {
    "id": 1,
    "name": "Pro",
    "description": "Professional plan with advanced features",
    "features": ["Feature 1", "Feature 2", "Feature 3"],
    "is_active": true,
    "created_at": "2026-04-06T10:00:00Z"
  }
}
```

---

#### 3. Get Plan Details
```http
GET /api/plans/{id}
Authorization: Bearer {token}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Pro",
    "description": "Professional plan",
    "features": ["Feature 1", "Feature 2"],
    "is_active": true,
    "billing_cycles": [
      {
        "id": 1,
        "cycle_type": "monthly",
        "display_name": "Monthly",
        "duration_in_days": 30,
        "prices": [
          {
            "id": 1,
            "currency": "USD",
            "price": "29.99",
            "is_active": true
          },
          {
            "id": 2,
            "currency": "AED",
            "price": "110.00",
            "is_active": true
          }
        ]
      }
    ]
  }
}
```

---

#### 4. Add Billing Cycle to Plan
```http
POST /api/plans/{id}/billing-cycles
Authorization: Bearer {token}
Content-Type: application/json

{
  "cycle_type": "monthly",
  "duration_in_days": 30,
  "display_name": "Monthly"
}
```

**Validation**:
- `cycle_type`: required|in:daily,weekly,monthly,quarterly,semi_annual,yearly
- `duration_in_days`: required|integer|min:1
- `display_name`: nullable|string|max:50

**Response (201 Created)**:
```json
{
  "success": true,
  "message": "Billing cycle added successfully",
  "data": {
    "id": 1,
    "plan_id": 1,
    "cycle_type": "monthly",
    "duration_in_days": 30,
    "display_name": "Monthly",
    "created_at": "2026-04-06T10:00:00Z"
  }
}
```

---

#### 5. Add Pricing for Billing Cycle
```http
POST /api/plans/{id}/pricing
Authorization: Bearer {token}
Content-Type: application/json

{
  "plan_billing_cycle_id": 1,
  "currency": "USD",
  "price": 29.99
}
```

**Validation**:
- `plan_billing_cycle_id`: required|exists:plan_billing_cycles
- `currency`: required|string|size:3|uppercase
- `price`: required|numeric|min:0

**Response (201 Created)**:
```json
{
  "success": true,
  "message": "Pricing added successfully",
  "data": {
    "id": 1,
    "plan_billing_cycle_id": 1,
    "currency": "USD",
    "price": "29.99",
    "is_active": true,
    "created_at": "2026-04-06T10:00:00Z"
  }
}
```

---

### Subscriptions

#### 1. List User Subscriptions
```http
GET /api/subscriptions?page=1&per_page=15
Authorization: Bearer {token}
```

**Query Parameters**:
- `page`: integer|min:1
- `per_page`: integer|min:1|max:50

**Response (200 OK)**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "plan": {
        "id": 1,
        "name": "Pro",
        "description": "Professional plan",
        "features": ["Feature 1"]
      },
      "billing_cycle": {
        "type": "monthly",
        "display_name": "Monthly",
        "duration_days": 30
      },
      "pricing": {
        "amount": "29.99",
        "currency": "USD",
        "formatted": "29.99 USD"
      },
      "status": "active",
      "status_label": "Active",
      "trial_ends_at": null,
      "started_at": "2026-04-06T10:00:00Z",
      "current_period_start": "2026-04-06T10:00:00Z",
      "current_period_end": "2026-05-06T10:00:00Z",
      "ends_at": null,
      "grace_period_ends_at": null,
      "canceled_at": null,
      "cancellation_reason": null,
      "is_in_grace_period": false,
      "grace_remaining_days": 0,
      "can_cancel": true,
      "can_change_plan": true,
      "recent_history": [
        {
          "id": 1,
          "previous_status": null,
          "new_status": "active",
          "reason": "Subscription created",
          "metadata": {
            "plan_name": "Pro",
            "billing_cycle": "monthly",
            "currency": "USD",
            "price": "29.99"
          },
          "created_at": "2026-04-06T10:00:00Z"
        }
      ],
      "created_at": "2026-04-06T10:00:00Z",
      "updated_at": "2026-04-06T10:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

---

#### 2. Create Subscription
```http
POST /api/subscriptions
Authorization: Bearer {token}
Content-Type: application/json

{
  "plan_id": 1,
  "plan_billing_cycle_id": 1,
  "currency": "USD",
  "trial_period_days": 14
}
```

**Validation**:
- `plan_id`: required|exists:plans
- `plan_billing_cycle_id`: required|exists:plan_billing_cycles|belongs to plan
- `currency`: required|string|size:3|uppercase
- `trial_period_days`: nullable|integer|min:0|max:365

**Response (201 Created)**:
```json
{
  "success": true,
  "message": "Subscription created successfully",
  "data": {
    "id": 1,
    "status": "trialing",
    "status_label": "In Trial",
    "trial_ends_at": "2026-04-20T10:00:00Z",
    "started_at": "2026-04-06T10:00:00Z",
    "current_period_end": "2026-04-20T10:00:00Z",
    "plan": { ... },
    "billing_cycle": { ... },
    "pricing": { ... }
  }
}
```

---

#### 3. Get Subscription Details
```http
GET /api/subscriptions/{id}
Authorization: Bearer {token}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 1,
    "plan": { ... },
    "billing_cycle": { ... },
    "pricing": { ... },
    "status": "active",
    "status_label": "Active",
    "trial_ends_at": null,
    "started_at": "2026-04-06T10:00:00Z",
    "current_period_start": "2026-04-06T10:00:00Z",
    "current_period_end": "2026-05-06T10:00:00Z",
    "ends_at": null,
    "grace_period_ends_at": null,
    "canceled_at": null,
    "cancellation_reason": null,
    "is_in_grace_period": false,
    "grace_remaining_days": 0,
    "can_cancel": true,
    "can_change_plan": true,
    "recent_history": [ ... ],
    "created_at": "2026-04-06T10:00:00Z",
    "updated_at": "2026-04-06T10:00:00Z"
  }
}
```

---

#### 4. Update Subscription (Change Plan/Cycle)
```http
PUT /api/subscriptions/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "plan_id": 2,
  "plan_billing_cycle_id": 3,
  "currency": "USD"
}
```

**Validation**:
- `plan_id`: sometimes|exists:plans
- `plan_billing_cycle_id`: sometimes|exists:plan_billing_cycles
- `currency`: sometimes|string|size:3|uppercase

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "Subscription updated successfully",
  "data": { ... }
}
```

---

#### 5. Cancel Subscription
```http
POST /api/subscriptions/{id}/cancel
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "No longer needed"
}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "Subscription canceled successfully",
  "data": {
    "id": 1,
    "status": "canceled",
    "status_label": "Canceled",
    "canceled_at": "2026-04-06T10:00:00Z",
    "cancellation_reason": "No longer needed",
    "ends_at": "2026-05-06T10:00:00Z"
  }
}
```

---

#### 6. Retry Payment
```http
POST /api/subscriptions/{id}/retry-payment
Authorization: Bearer {token}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "Payment retry processed",
  "data": {
    "id": 1,
    "status": "active",
    "status_label": "Active",
    "grace_period_ends_at": null
  }
}
```

---

#### 7. Change Billing Cycle
```http
PUT /api/subscriptions/{id}/billing-cycle
Authorization: Bearer {token}
Content-Type: application/json

{
  "plan_billing_cycle_id": 2,
  "currency": "USD"
}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "Billing cycle changed successfully",
  "data": { ... }
}
```

---

#### 8. Get Subscription History
```http
GET /api/subscriptions/{id}/history
Authorization: Bearer {token}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "subscription_id": 1,
      "previous_status": null,
      "new_status": "trialing",
      "reason": "Subscription created with trial",
      "metadata": {
        "plan_name": "Pro",
        "billing_cycle": "monthly",
        "currency": "USD",
        "price": "29.99",
        "trial_days": 14
      },
      "created_at": "2026-04-06T10:00:00Z"
    },
    {
      "id": 2,
      "subscription_id": 1,
      "previous_status": "trialing",
      "new_status": "active",
      "reason": "Trial ended",
      "metadata": {
        "transition_type": "trial_activation",
        "timestamp": "2026-04-20T10:00:00Z",
        "automated": true
      },
      "created_at": "2026-04-20T10:00:00Z"
    }
  ]
}
```

---

#### 9. Get Status Info
```http
GET /api/subscriptions/{id}/status-info
Authorization: Bearer {token}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "status": "past_due",
    "label": "Past Due",
    "is_trialing": false,
    "is_active": false,
    "is_past_due": true,
    "is_canceled": false,
    "is_in_grace_period": true,
    "grace_remaining_days": 2,
    "grace_remaining_hours": 48,
    "can_cancel": true,
    "can_change_plan": false,
    "valid_transitions": ["active", "canceled"],
    "reason": "Payment failed: Card declined",
    "last_payment_attempt": "2026-04-05T10:00:00Z"
  }
}
```

---

### Webhooks

#### 1. Payment Success
```http
POST /api/webhooks/payment-success
Content-Type: application/json

{
  "subscription_id": 1,
  "transaction_id": "txn_12345",
  "amount": 29.99,
  "currency": "USD",
  "payment_method": "card",
  "processed_at": "2026-04-06T10:00:00Z"
}
```

**Validation**:
- `subscription_id`: required|integer|exists:subscriptions
- `transaction_id`: nullable|string|max:255
- `amount`: nullable|numeric|min:0
- `currency`: nullable|string|size:3|uppercase
- `payment_method`: nullable|string|max:50
- `processed_at`: nullable|date

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "Payment success processed",
  "data": {
    "subscription_id": 1,
    "status": "active",
    "current_period_end": "2026-05-06T10:00:00Z"
  }
}
```

---

#### 2. Payment Failed
```http
POST /api/webhooks/payment-failed
Content-Type: application/json

{
  "subscription_id": 1,
  "transaction_id": "txn_12345",
  "amount": 29.99,
  "currency": "USD",
  "failure_reason": "Card declined",
  "error_code": "card_declined",
  "error_message": "Your card was declined",
  "payment_method": "card",
  "failed_at": "2026-04-06T10:00:00Z"
}
```

**Validation**:
- `subscription_id`: required|integer|exists:subscriptions
- `failure_reason`: required|string|max:255
- `error_code`: nullable|string|max:50
- `error_message`: nullable|string|max:1000
- Other fields: optional

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "Payment failure processed",
  "data": {
    "subscription_id": 1,
    "status": "past_due",
    "grace_period_ends_at": "2026-04-09T10:00:00Z",
    "grace_remaining_days": 3
  }
}
```

---

## 🔐 Authentication & Authorization

### Sanctum API Tokens

**How It Works**:
1. User registers or logs in
2. Server creates a personal access token
3. Token is returned to client
4. Client includes token in `Authorization: Bearer {token}` header

**Token Lifecycle**:
```php
// Create token on login/register
$token = $user->createToken('api-token')->plainTextToken;

// Use token in requests
Authorization: Bearer 1|abc123xyz...

// List user's tokens
$tokens = $user->tokens();

// Revoke specific token
$user->tokens()->where('name', 'api-token')->delete();

// Logout (revokes current token)
$user->currentAccessToken()->delete();
```

**Protected Routes**:
- All `/api/auth` (except register/login)
- All `/api/plans/*` (except public endpoints)
- All `/api/subscriptions/*`

**Public Routes**:
- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/webhooks/*` (webhook integrations)

---

## 📋 Controllers Summary

### AuthController
- `register(Request)`: Create user account
- `login(Request)`: Authenticate user
- `logout(Request)`: Revoke current token
- `tokens(Request)`: List user's tokens
- `refresh(Request)`: Get new token

### PlanController
- `index(Request)`: List active plans with pagination
- `store(StorePlanRequest)`: Create new plan
- `show($id)`: Get plan with all pricing
- `update(UpdatePlanRequest, $id)`: Update plan
- `destroy($id)`: Delete plan
- `addBillingCycle(Request, $id)`: Add cycle to plan
- `addPricing(Request, $id)`: Add pricing to cycle
- `getPricing(Request, $id)`: Get all pricing for plan

### SubscriptionController
- `index(Request)`: List user's subscriptions
- `store(CreateSubscriptionRequest)`: Create new subscription
- `show(Subscription)`: Get subscription details
- `update(Request, Subscription)`: Update subscription
- `destroy(Subscription)`: Delete subscription (soft)
- `cancel(Request, Subscription)`: Cancel subscription
- `retryPayment(Subscription)`: Retry failed payment
- `changeBillingCycle(Request, Subscription)`: Change cycle
- `history(Subscription)`: Get state transition history
- `statusInfo(Subscription)`: Get status and actions

### WebhookController
- `paymentSuccess(Request)`: Handle payment success
- `paymentFailed(Request)`: Handle payment failure
- `paymentRecovered(Request)`: Handle payment recovery
- `subscriptionCancelled(Request)`: Handle cancellation
- `genericWebhook(Request, $provider)`: Generic webhook

---

## 📊 Request/Response Formats

### Standard Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Standard Error Response
```json
{
  "success": false,
  "message": "Error description",
  "code": "ERROR_CODE",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### Pagination Response
```json
{
  "success": true,
  "message": "Data retrieved",
  "data": [ ... ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7,
    "from": 1,
    "to": 15
  }
}
```

---

## 🔄 Workflows & Flows

### Workflow 1: Create Subscription with Trial
```
1. User registers → get token
2. User browses plans (GET /api/plans)
3. Select plan and billing cycle
4. Create subscription with trial
   - Status: trialing
   - Trial ends in 14 days
5. Full access to features during trial
6. Trial approaches end → send notification
7. Trial ends → status changes to active
   - Scheduled task: ExpireTrialsCommand runs daily
8. User can cancel anytime before/during trial
```

**Timeline**:
```
Day 1: Create subscription (trialing)
↓
Day 14: Trial expires
↓
Day 15: Status becomes active (if not canceled)
↓
Day 44: Current period ends (30-day billing cycle)
↓
Day 45: Next billing cycle starts
```

---

### Workflow 2: Payment Recovery (Grace Period)
```
1. Subscription is active
2. Payment fails (webhook received)
   - Status: past_due
   - Grace period begins (3 days)
   - grace_period_ends_at = now + 3 days
   - FailedPayment record created
3. User receives notification about failed payment
4. User retries payment (within 3 days)
   - Payment succeeds
   - Status: active
   - Grace period cleared
   - FailedPayment marked as recovered
   - All features resume
5. If grace period expires without recovery
   - Scheduled task: ProcessGracePeriodCommand
   - Status: canceled
   - Subscription ends
```

**Timeline**:
```
Day 1, 10:00 AM: Payment fails
                 Status: past_due
                 grace_period_ends_at = Day 4, 10:00 AM

Day 2, 2:00 PM:  User retries payment
                 Status: active
                 Grace period cleared

--- OR ---

Day 4, 10:01 AM: Grace period expires
                 Scheduled task runs
                 Status: canceled
```

---

### Workflow 3: Subscription Cancellation
```
1. User requests cancellation (POST /api/subscriptions/{id}/cancel)
2. Check if cancellation is allowed (can_cancel = true)
3. Mark subscription as canceled
   - Status: canceled
   - canceled_at = now
   - cancellation_reason = provided reason
   - ends_at = current_period_end
4. Record state transition with reason
5. User loses access at current_period_end
6. Send cancellation confirmation email
```

---

### Workflow 4: Plan Change
```
1. User requests plan change (PUT /api/subscriptions/{id})
2. Validate new plan and billing cycle
3. Calculate proration:
   - Remaining days in current period
   - Pro-rate old plan charges
   - Credit amount for new plan
4. Update subscription:
   - plan_id = new plan
   - plan_billing_cycle_id = new cycle
   - plan_price_id = new price
   - current_period_end = new end date
5. Record history with reason "Plan changed"
6. Charge the difference or credit to account
```

---

## 🎯 Key Features & Business Logic

### 1. Multi-Currency Support
- Supports: AED, USD, EGP (extensible)
- Prices stored as DECIMAL(12, 2) for precision
- Format: 29.99 USD, 110.00 AED, 500.00 EGP

### 2. Flexible Billing Cycles
- daily, weekly, monthly, quarterly, semi_annual, yearly
- Configurable duration in days
- Unique pricing per cycle per plan

### 3. Trial Period
- Optional, configurable per subscription
- Default: 14 days
- Can be 0-365 days
- Full feature access during trial
- Automatic conversion to active after trial

### 4. Grace Period
- Duration: 3 days (hardcoded in GracePeriodService)
- Triggered when payment fails
- User can still access features
- Automatic cancellation if not recovered

### 5. State Machine
- Trialing → Active → Past Due → Canceled
- Validated transitions only
- Automatic history tracking
- Metadata on each transition

### 6. Soft Deletes
- Subscriptions use soft deletes
- Historical data preserved
- Can be restored if needed

### 7. Audit Trail
- Every state transition logged
- Metadata includes reason, timestamp, automated flag
- Complete history available for compliance

---

## 🗂️ Request Files

### StorePlanRequest
```php
Validates:
- name (required, unique, max:255)
- description (nullable, max:1000)
- features (nullable array of strings)
```

### CreateSubscriptionRequest
```php
Validates:
- plan_id (required, exists)
- plan_billing_cycle_id (required, exists, belongs to plan)
- currency (required, 3 chars)
- trial_period_days (optional, 0-365)
```

### StorePlanPricingRequest
```php
Validates:
- plan_billing_cycle_id (required, exists)
- currency (required, 3 chars)
- price (required, numeric, min:0)
```

---

## 📋 Resource/Response Files

### SuccessResponse
```php
static methods:
- make($data, $message, $statusCode): array
- collection($data, $message, $pagination): array
- created($data, $message): array
- updated($data, $message): array
- deleted($message): array
```

### ErrorResponse
```php
static methods:
- make($message, $errors, $code, $statusCode): array
- notFound($resource): array
- validation($errors): array
- unauthorized(): array
```

---

## 🛠️ Technical Stack

### Languages & Frameworks
- **PHP**: 8.2+
- **Laravel**: 11.x
- **Database**: MySQL 8.0+

### Key Dependencies
```json
{
  "php": "^8.2",
  "laravel/framework": "^12.0",
  "laravel/sanctum": "^4.3",
  "laravel/tinker": "^2.10.1"
}
```

### Dev Dependencies
- **PHPUnit**: ^11.5.50 - Unit testing
- **Faker**: ^1.23 - Test data generation
- **Mockery**: ^1.6 - Mocking framework
- **Laravel Pint**: ^1.24 - Code style
- **Laravel Pail**: ^1.2.2 - Log viewing

---

## 📦 Architecture Patterns

### 1. Service Layer Pattern
- Controllers delegate to services
- Services contain business logic
- Models handle data access
- Clean separation of concerns

### 2. Repository Pattern (via Eloquent)
- Models act as repositories
- Query scopes for common filters
- Relationships for data access

### 3. Action Classes (Implicit)
- Services group related actions
- Each method is a use case
- Transactions ensure consistency

### 4. Enum Pattern
- SubscriptionState as PHP enum
- Type-safe state management
- Built-in validation of transitions

### 5. Observer Pattern (Implicit)
- Webhooks trigger state changes
- Events flow through service layer
- History automatically tracked

---

## 🔑 Key Configuration

### Database Configuration
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_lifecycle
DB_USERNAME=root
DB_PASSWORD=
```

### Application Configuration
```env
APP_NAME="Subscription Lifecycle Engine"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=UTC
```

### API Configuration
```php
// Sanctum tokens
SANCTUM_STATEFUL_DOMAINS=localhost:3000

// Grace period (hardcoded in GracePeriodService)
DEFAULT_GRACE_PERIOD_DAYS=3

// Default trial period
DEFAULT_TRIAL_DAYS=14
```

---

## 🚀 Deployment Checklist

### Pre-Deployment
- [ ] All tests passing
- [ ] Environment variables configured
- [ ] Database backups created
- [ ] Migrations tested in staging

### Deployment Steps
1. Clone repository
2. Install dependencies: `composer install`
3. Setup environment: `cp .env.example .env`
4. Generate key: `php artisan key:generate`
5. Run migrations: `php artisan migrate`
6. Configure scheduler in crontab
7. Start application: `php artisan serve`

### Post-Deployment
- [ ] Health check endpoints
- [ ] Webhook testing
- [ ] Payment flow testing
- [ ] Monitor logs: `php artisan pail`
- [ ] Set up monitoring/alerting

---

### Available Commands
```bash
# Existing Laravel commands
php artisan migrate          # Run migrations
php artisan tinker          # Interactive shell
php artisan queue:listen    # Process jobs

# Custom commands
php artisan schedule:run    # Run scheduler (cron-triggered)
```

### Security Considerations
- Sanctum tokens expire after use if stateless
- All user input validated through FormRequests
- Soft deletes preserve historical data
- Transactions ensure data consistency
- Logging for audit trail


---

## 📊 Database Relationships Diagram

```
users (1)
  ├── (∞) subscriptions
        ├── plan
        ├── plan_billing_cycle
        ├── plan_price
        ├── (∞) subscription_histories
        └── (∞) failed_payments

plans (1)
  ├── (∞) plan_billing_cycles
  │         ├── (∞) plan_prices
  │         │         └── (∞) subscriptions
  │         └── (∞) subscriptions
  └── (∞) subscriptions
```

*Generated: April 6, 2026*  
*Project: Subscription Lifecycle Engine*  
*Framework: Laravel 11 | PHP 8.2+ | MySQL 8.0+*
