# 📊 Subscription Lifecycle Engine

> A production-grade **Subscription Management System** built with Laravel 11, featuring dynamic plans, multi-currency pricing, and sophisticated lifecycle automation with grace periods and state machine transitions.

[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue.svg)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-orange.svg)](https://www.mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## 🎯 Overview

The **Subscription Lifecycle Engine** is a complete solution for managing subscription-based services with:

- ✅ **Dynamic Plans** - Create flexible subscription plans with multiple billing cycles
- ✅ **Multi-Currency Support** - AED, USD, EGP, and extensible to any currency
- ✅ **State Machine** - 4 states (trialing, active, past_due, canceled) with validated transitions
- ✅ **Grace Periods** - 3-day recovery window for failed payments with full feature access
- ✅ **Automated Scheduling** - Daily cron jobs for trial expiry and grace period processing
- ✅ **Payment Webhooks** - Handle payment success/failure events from payment providers
- ✅ **Audit Trail** - Complete subscription history for compliance and debugging
- ✅ **Clean Architecture** - Separation of concerns with service layer pattern

**Perfect for:**
- SaaS platforms
- Subscription-based products
- Freemium models with trials
- B2B billing systems

---

## 🚀 Quick Start

### Prerequisites

- PHP 8.3+
- Laravel 11.x
- MySQL 8.0+
- Composer
- Node.js & npm (for frontend, optional)

### Installation

1. **Clone the repository**
```bash
git clone <repository-url>
cd "Subscription Lifecycle Engine"
```

2. **Install dependencies**
```bash
composer install
npm install && npm run build  # Optional: for frontend assets
```

3. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure database** (in `.env`)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_lifecycle
DB_USERNAME=root
DB_PASSWORD=
```

5. **Run migrations**
```bash
php artisan migrate
```

6. **Start the application**
```bash
php artisan serve
# Application available at http://localhost:8000
```

7. **Configure scheduler** (for production)
```bash
# Add to crontab
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 📊 Architecture Overview

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
│  ├── SubscriptionStateService (State Machine)                │
│  ├── GracePeriodService                                       │
│  └── PaymentService                                           │
├─────────────────────────────────────────────────────────────┤
│              Eloquent Models & Relationships                   │
│  ├── Plan, PlanBillingCycle, PlanPrice                       │
│  ├── Subscription, SubscriptionHistory                        │
│  └── FailedPayment                                            │
├─────────────────────────────────────────────────────────────┤
│                   Database Layer (MySQL)                      │
└─────────────────────────────────────────────────────────────┘
        ↓
    Laravel Scheduler (Daily Cron)
    ├── ExpireTrialsCommand (00:01 UTC)
    └── ProcessGracePeriodCommand (01:00 UTC)
```

### Technology Stack

| Component | Technology | Purpose |
|-----------|-----------|---------|
| Framework | Laravel 11 | Modern PHP web framework |
| Database | MySQL 8.0+ | Relational data storage |
| ORM | Eloquent | Object-relational mapping |
| Authentication | Sanctum | API token authentication |
| State Management | PHP Enums | Type-safe state machine |
| Scheduling | Laravel Scheduler | Automated cron jobs |
| Validation | Form Requests | Request validation & authorization |
| Timestamps | Carbon | Date/time handling with UTC |

---

## 💾 Database Schema

### 6 Core Tables

1. **plans** - Subscription plan definitions
2. **plan_billing_cycles** - Billing cycles per plan (daily, weekly, monthly, etc.)
3. **plan_prices** - Multi-currency pricing
4. **subscriptions** - Active subscriptions with state tracking
5. **subscription_histories** - Audit trail of all state changes
6. **failed_payments** - Payment failure tracking for analytics

### Entity Relationship Diagram

```
users (Laravel)
  ↓ 1:N
subscriptions ──→ subscription_histories
  ↓ N:1
  ├── plans
  ├── plan_billing_cycles
  ├── plan_prices
  └── failed_payments
```

For detailed schema, see [PLAN.md](PLAN.md#-database-schema-design).

---

## 🔄 State Machine

### Subscription States

```
┌──────────────────┐
│   TRIALING       │  Trial period active
│  (free access)   │
└────────┬─────────┘
         │ trial_ends_at
         ↓
┌──────────────────┐
│    ACTIVE        │  Paid subscription
│  (full access)   │
└────────┬─────────┘
         │ payment fails
         ↓
┌──────────────────┐
│   PAST_DUE       │  Grace period (3 days)
│  (access kept!)  │  recovery window
└────────┬─────────┘
         │ grace_period_ends_at
         ↓
┌──────────────────┐
│   CANCELED       │  Terminal state
│  (no access)     │
└──────────────────┘
```

### Valid Transitions

| From | To | Trigger |
|------|-----|---------|
| TRIALING | ACTIVE | Trial expires (automatic) |
| TRIALING | CANCELED | User cancels |
| ACTIVE | PAST_DUE | Payment fails |
| ACTIVE | CANCELED | User cancels |
| PAST_DUE | ACTIVE | Payment recovered |
| PAST_DUE | CANCELED | Grace period expires OR user cancels |
| CANCELED | — | Terminal state |

---

## 🎯 Grace Period Flow

**Key Feature:** Users keep full access during the grace period!

```
Payment Fails
    ↓
Move to PAST_DUE
Set grace_period_ends_at = now + 3 days
Send email to user
    ↓
    ├─→ [User Retries Payment] ──→ SUCCESS ──→ ACTIVE (immediate)
    │
    └─→ [Wait 3 Days] ──→ ProcessGracePeriodCommand runs
                          ↓
                      CANCELED
                      Revoke access
                      Send notification
```

**Example Timeline:**
- Apr 5: Payment fails → PAST_DUE (grace until Apr 8)
- Apr 6: User updates payment method
- Apr 7: Retry succeeds → Back to ACTIVE immediately
- Access never interrupted! ✅

---

## 📡 API Endpoints

### Plans Management

```
GET     /api/v1/plans                          List all active plans
POST    /api/v1/plans                          Create new plan
GET     /api/v1/plans/{id}                     Get plan details
PUT     /api/v1/plans/{id}                     Update plan
DELETE  /api/v1/plans/{id}                     Deactivate plan

POST    /api/v1/plans/{id}/billing-cycles     Add billing cycle
POST    /api/v1/plans/{id}/pricing            Add pricing
GET     /api/v1/plans/{id}/pricing            Get plan pricing
```

### Subscriptions

```
GET     /api/v1/subscriptions                  List user subscriptions (requires auth)
POST    /api/v1/subscriptions                  Create subscription (requires auth)
GET     /api/v1/subscriptions/{id}             Get subscription details (requires auth)
PUT     /api/v1/subscriptions/{id}             Update subscription (requires auth)
DELETE  /api/v1/subscriptions/{id}             Cancel subscription (requires auth)

POST    /api/v1/subscriptions/{id}/cancel                  Cancel immediately
GET     /api/v1/subscriptions/{id}/history                 Get state history
POST    /api/v1/subscriptions/{id}/retry-payment           Retry failed payment
GET     /api/v1/subscriptions/{id}/status-info             Get status details
```

### Webhooks (Public - No Auth)

```
POST    /api/v1/webhooks/payment-success       Payment successful
POST    /api/v1/webhooks/payment-failed        Payment failed
POST    /api/v1/webhooks/payment-recovered     Payment recovered in grace period
```

---

## 🧪 API Examples

### 1. Create a Plan

```bash
curl -X POST http://localhost:8000/api/v1/plans \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Professional",
    "description": "For growing teams",
    "features": ["Advanced analytics", "API access", "Priority support"]
  }'
```

**Response:**
```json
{
  "id": 1,
  "name": "Professional",
  "description": "For growing teams",
  "features": ["Advanced analytics", "API access", "Priority support"],
  "is_active": true,
  "created_at": "2026-04-05T10:00:00Z",
  "updated_at": "2026-04-05T10:00:00Z"
}
```

### 2. Add Billing Cycle

```bash
curl -X POST http://localhost:8000/api/v1/plans/1/billing-cycles \
  -H "Content-Type: application/json" \
  -d '{
    "cycle_type": "monthly",
    "duration_in_days": 30,
    "display_name": "Monthly"
  }'
```

### 3. Add Pricing (Multi-Currency)

```bash
curl -X POST http://localhost:8000/api/v1/plans/1/pricing \
  -H "Content-Type: application/json" \
  -d '{
    "plan_billing_cycle_id": 1,
    "currency": "USD",
    "price": 99.99
  }'

# Add AED pricing for same cycle
curl -X POST http://localhost:8000/api/v1/plans/1/pricing \
  -H "Content-Type: application/json" \
  -d '{
    "plan_billing_cycle_id": 1,
    "currency": "AED",
    "price": 367.00
  }'
```

### 4. Create Subscription

```bash
curl -X POST http://localhost:8000/api/v1/subscriptions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "plan_id": 1,
    "plan_billing_cycle_id": 1,
    "currency": "USD",
    "trial_period_days": 14
  }'
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

### 5. Payment Failure Webhook

```bash
curl -X POST http://localhost:8000/api/v1/webhooks/payment-failed \
  -H "Content-Type: application/json" \
  -d '{
    "subscription_id": 5,
    "amount": 99.99,
    "currency": "USD",
    "reason": "card_declined",
    "error_code": "card_declined",
    "error_message": "Your card was declined."
  }'
```

**System Actions:**
- Status changes to PAST_DUE
- Grace period set to 3 days
- Email sent to user
- Access remains active ✅
- Can retry payment anytime

### 6. Retry Payment During Grace Period

```bash
curl -X POST http://localhost:8000/api/v1/subscriptions/5/retry-payment \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "payment_method_id": "pm_1234567890"
  }'
```

**Result:** Subscription immediately transitions back to ACTIVE

---

## 🔐 Service Layer

### PlanService
Manages subscription plans, billing cycles, and multi-currency pricing.

**Key Methods:**
```php
$planService->createPlan(['name' => 'Pro', ...])
$planService->addBillingCycle($plan, 'monthly', 30)
$planService->addPricing($cycle, 'USD', 99.99)
$planService->getPricingByCurrencyAndCycle($plan, 'USD', 'monthly')
$planService->listActivePlans()
```

### SubscriptionService
Handles subscription creation, updates, and lifecycle operations.

**Key Methods:**
```php
$subService->createSubscription($user, $plan, $cycle, 'USD', true, 14)
$subService->getUserSubscriptions($user)
$subService->getSubscriptionDetails($subscription)
$subService->calculateNextBillingDate($subscription)
$subService->changeSubscriptionPlan($subscription, $newPlan, $newCycle)
```

### SubscriptionStateService
Implements the state machine with transition validation.

**Key Methods:**
```php
$stateService->activate($subscription)           // TRIALING → ACTIVE
$stateService->markPastDue($subscription, 'reason')  // ACTIVE → PAST_DUE
$stateService->recover($subscription)            // PAST_DUE → ACTIVE
$stateService->cancel($subscription, 'reason')   // ANY → CANCELED
$stateService->canTransitionTo($subscription, $newState)
```

### GracePeriodService
Manages grace period logic and expiry processing.

**Key Methods:**
```php
$graceService->startGracePeriod($subscription, 3)  // Start 3-day grace
$graceService->isInGracePeriod($subscription)      // Check if in grace
$graceService->getGraceRemainingDays($subscription)
$graceService->processExpiredGracePeriods()    // Called by scheduler
$graceService->getGracePeriodStats()
```

### PaymentService
Handles payment webhooks and failed payment tracking.

**Key Methods:**
```php
$paymentService->handlePaymentSuccess($subscription)
$paymentService->handlePaymentFailure($subscription, 'reason')
$paymentService->recordFailedPayment($subscription, 99.99, 'USD', 'reason')
$paymentService->retryFailedPayment($failedPayment)
$paymentService->getPaymentStats()
```

---

## ⏰ Automated Scheduling

### Daily Commands

#### ExpireTrialsCommand
**Schedule:** Daily at 00:01 UTC

```bash
php artisan subscriptions:expire-trials
php artisan subscriptions:expire-trials --dry-run  # Test mode
```

**Logic:**
- Finds subscriptions where `trial_ends_at <= now`
- Transitions them from TRIALING to ACTIVE
- Records state change in audit trail
- Sends confirmation email

#### ProcessGracePeriodCommand
**Schedule:** Daily at 01:00 UTC

```bash
php artisan subscriptions:process-grace-period
php artisan subscriptions:process-grace-period --dry-run  # Test mode
```

**Logic:**
- Finds subscriptions where `grace_period_ends_at <= now`
- Cancels them (PAST_DUE → CANCELED)
- Records cancellation reason
- Sends final notification email

**Idempotency:** Both commands check `updated_at` to prevent duplicate processing.

---

## 🗂️ Project Structure

```
.
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── ExpireTrialsCommand.php
│   │       └── ProcessGracePeriodCommand.php
│   ├── Enums/
│   │   └── SubscriptionState.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── PlanController.php
│   │   │   ├── SubscriptionController.php
│   │   │   └── WebhookController.php
│   │   └── Requests/
│   │       ├── CreatePlanRequest.php
│   │       └── CreateSubscriptionRequest.php
│   ├── Models/
│   │   ├── Plan.php
│   │   ├── PlanBillingCycle.php
│   │   ├── PlanPrice.php
│   │   ├── Subscription.php
│   │   ├── SubscriptionHistory.php
│   │   └── FailedPayment.php
│   └── Services/
│       ├── PlanService.php
│       ├── SubscriptionService.php
│       ├── SubscriptionStateService.php
│       ├── GracePeriodService.php
│       └── PaymentService.php
├── database/
│   └── migrations/
│       ├── 2026_04_05_000001_create_plans_table.php
│       ├── 2026_04_05_000002_create_plan_billing_cycles_table.php
│       ├── 2026_04_05_000003_create_plan_prices_table.php
│       ├── 2026_04_05_000004_create_subscriptions_table.php
│       ├── 2026_04_05_000005_create_subscription_histories_table.php
│       └── 2026_04_05_000006_create_failed_payments_table.php
├── routes/
│   └── api.php
├── bootstrap/
│   └── app.php
├── PLAN.md                 # Detailed implementation plan
├── README.md               # This file
└── .env.example
```

---

## 🧪 Testing

### Test Scheduled Commands

```bash
# Test trial expiry (dry-run)
php artisan subscriptions:expire-trials --dry-run

# Test grace period processing (dry-run)
php artisan subscriptions:process-grace-period --dry-run

# View scheduler
php artisan schedule:list
```

### Verify State Transitions

```bash
# In tinker shell
php artisan tinker

> $sub = \App\Models\Subscription::first()
> $sub->status
> \App\Services\SubscriptionStateService::canTransitionTo($sub, \App\Enums\SubscriptionState::ACTIVE)
```

---

## 🎯 Key Design Decisions

### 1. **Enum-based State Machine**
- Type-safe state management
- Validation at enum level
- Eliminates invalid state strings

### 2. **Service Layer Pattern**
- Business logic separated from controllers
- Reusable across API & CLI
- Easy to test and maintain

### 3. **Grace Period with Full Access**
- Users keep full feature access during grace period
- Higher payment recovery rates
- Better user experience
- Reduces churn

### 4. **Audit Trail**
- Every state change recorded
- Full subscription history
- Compliance & debugging support

### 5. **Timezone Safety**
- All timestamps in UTC
- Carbon ensures consistency
- Prevents timezone bugs

### 6. **Idempotent Scheduling**
- Safe to run multiple times
- Prevents duplicate processing
- Production-ready

---

## ⚠️ Important Configuration

### .env Settings

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=subscription_lifecycle
DB_USERNAME=root
DB_PASSWORD=

# API
APP_URL=http://localhost:8000
APP_DEBUG=false

# Timezone (must be UTC for consistency)
APP_TIMEZONE=UTC
```

### Scheduler Configuration

The scheduler is configured in `bootstrap/app.php`:

```php
->withSchedule(function (Schedule $schedule) {
    $schedule->command('subscriptions:expire-trials')
        ->dailyAt('00:01')
        ->timezone('UTC');
    
    $schedule->command('subscriptions:process-grace-period')
        ->dailyAt('01:00')
        ->timezone('UTC');
})
```

---

## 🚀 Production Deployment

### 1. Environment

```bash
cp .env.example .env
# Edit .env with production values
php artisan key:generate
php artisan migrate --force
```

### 2. Install Cron

Add to crontab:
```bash
* * * * * cd /var/www/subscription-engine && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Setup Supervisor (Optional)

For queue processing:
```ini
[program:subscription-engine-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/subscription-engine/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=8
redirect_stderr=true
stdout_logfile=/var/log/subscription-engine-queue.log
```

### 4. Monitoring

```bash
# Monitor log files
tail -f storage/logs/laravel.log

# Check scheduler execution
php artisan schedule:work

# Monitor failed jobs
php artisan queue:failed
```

---

## 📚 Additional Documentation

- **[PLAN.md](PLAN.md)** - Complete implementation plan with schema design
- **[API.md](API.md)** - Detailed API endpoint reference (coming soon)
- **[ARCHITECTURE.md](ARCHITECTURE.md)** - Deep dive into system design (coming soon)
- **[DECISIONS.md](DECISIONS.md)** - Design decisions & tradeoffs (coming soon)
- **[Postman Collection](postman-collection.json)** - Ready-to-use API tests (coming soon)

---

## 🤝 Contributing

Contributions are welcome! Please follow the architecture and patterns established in this codebase.

### Standards

- Follow PSR-12 coding standard
- Place business logic in services
- Use form requests for validation
- Write comprehensive comments
- Test edge cases

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## ❓ FAQ

**Q: Can I change the grace period duration?**  
A: Yes. Configure in `GracePeriodService::DEFAULT_GRACE_PERIOD_DAYS` or pass as parameter.

**Q: How do I add new currencies?**  
A: Simple! Just add pricing via the API with new currency code (e.g., 'EUR', 'GBP').

**Q: What happens if scheduler doesn't run?**  
A: Commands can be triggered manually via API or cron. Monitor with Supervisor for reliability.

**Q: Can subscriptions be downgraded mid-cycle?**  
A: Yes. `SubscriptionService::changeSubscriptionPlan()` handles it with immediate effect or cycle-end.

**Q: How do I test payment webhooks locally?**  
A: Use Postman collection or curl. Commands include `--dry-run` for safe testing.

---

**Version:** 1.0.0  
**Last Updated:** April 5, 2026  
**Status:** Production Ready ✅

For support or questions, refer to [PLAN.md](PLAN.md) or review the service layer documentation.
