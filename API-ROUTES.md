# 📡 API Routes & Documentation

Complete reference guide for all API endpoints, their instructions, and related code.

---

## 📋 Table of Contents

1. [Authentication](#authentication)
   - [User Registration](#user-registration)
   - [User Login](#user-login)
   - [Generate API Token](#generate-api-token)
   - [Using the Token](#using-the-token)
2. [Plans Endpoints](#plans-endpoints)
3. [Subscriptions Endpoints](#subscriptions-endpoints)
4. [Webhooks Endpoints](#webhooks-endpoints)
5. [Response Format](#response-format)
6. [Error Handling](#error-handling)
7. [Code Examples](#code-examples)

---

## 🔐 Authentication

Complete authentication flow from registration to API token generation.

### Step 1: User Registration

```
POST /api/auth/register
Content-Type: application/json
```

**Description:** Create a new user account

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!"
}
```

**Validation Rules:**
```php
'name' => 'required|string|max:255',
'email' => 'required|string|email|unique:users|max:255',
'password' => 'required|string|min:8|confirmed',
'password_confirmation' => 'required|string'
```

**Curl Example:**
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!"
  }'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2026-04-05T10:00:00Z"
  }
}
```

**Related Code:**
```php
// File: app/Http/Controllers/AuthController.php (if exists) or User Model
// Laravel's default registration handler
public function register(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|unique:users|max:255',
        'password' => 'required|string|min:8|confirmed',
    ]);
    
    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
    ]);
    
    return response()->json([
        'success' => true,
        'message' => 'User registered successfully',
        'data' => $user,
    ], 201);
}
```

---

### Step 2: User Login

```
POST /api/auth/login
Content-Type: application/json
```

**Description:** Authenticate user with email and password

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "SecurePassword123!"
}
```

**Validation Rules:**
```php
'email' => 'required|string|email',
'password' => 'required|string'
```

**Curl Example:**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "SecurePassword123!"
  }'
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "created_at": "2026-04-05T10:00:00Z"
    },
    "token": "1|e7aXxxx...xxxxxx"
  }
}
```

**Error Response (401 Unauthorized):**
```json
{
  "success": false,
  "message": "Invalid credentials",
  "code": "AUTH_FAILED"
}
```

**Related Code:**
```php
// File: app/Http/Controllers/AuthController.php
public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|string|email',
        'password' => 'required|string',
    ]);
    
    if (!Auth::attempt($credentials)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials',
            'code' => 'AUTH_FAILED',
        ], 401);
    }
    
    $user = Auth::user();
    $token = $user->createToken('api-token')->plainTextToken;
    
    return response()->json([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'user' => $user,
            'token' => $token,
        ],
    ]);
}
```

---

### Step 3: Generate API Token (Alternative Method)

If you already have a user authentication session, you can generate tokens via:

**Option A: Tinker (CLI)**

```bash
php artisan tinker
```

In the tinker prompt:
```php
> $user = User::find(1)
> $token = $user->createToken('api-token')->plainTextToken
=> "1|e7aXxxx...xxxxxx"

> exit
```

**Option B: Artisan Command (Direct)**

```bash
php artisan user:create-token {email} {device-name}

# Example:
php artisan user:create-token john@example.com "Mobile App"
```

**Option C: Database Query**

```sql
-- Find user
SELECT id, email FROM users WHERE email = 'john@example.com';

-- Then use Tinker to generate token:
php artisan tinker
> $user = User::find(1)
> $token = $user->createToken('api-token')->plainTextToken
```

---

### Step 4: Using the Token

#### In HTTP Headers

Include the token in the `Authorization` header for all authenticated requests:

```bash
curl -H "Authorization: Bearer 1|e7aXxxx...xxxxxx" \
  http://localhost:8000/api/subscriptions
```

#### In PHP/Laravel

```php
// Using HTTP client
$response = Http::withToken('1|e7aXxxx...xxxxxx')
    ->get('http://localhost:8000/api/subscriptions');
```

#### In JavaScript/Fetch

```javascript
const token = '1|e7aXxxx...xxxxxx';

fetch('http://localhost:8000/api/subscriptions', {
    method: 'GET',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
})
.then(response => response.json())
.then(data => console.log(data));
```

#### In Python/Requests

```python
import requests

token = '1|e7aXxxx...xxxxxx'
headers = {
    'Authorization': f'Bearer {token}',
    'Content-Type': 'application/json'
}

response = requests.get(
    'http://localhost:8000/api/subscriptions',
    headers=headers
)

print(response.json())
```

---

### Complete Authentication Flow

**Step-by-Step Example:**

1. **User registers:**
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!"
  }'
# Response: User created with ID 1
```

2. **User logs in:**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "SecurePassword123!"
  }'
# Response: Token = "1|e7aXxxx...xxxxxx"
```

3. **Use token to access API:**
```bash
TOKEN="1|e7aXxxx...xxxxxx"

# Create a plan
curl -X POST http://localhost:8000/api/plans \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Professional",
    "description": "For growing teams",
    "features": ["Advanced analytics", "API access"]
  }'
# Response: Plan created successfully
```

4. **Get subscriptions (authenticated request):**
```bash
curl -X GET http://localhost:8000/api/subscriptions \
  -H "Authorization: Bearer $TOKEN"
# Response: List of subscriptions
```

---

### Token Management

#### Revoke Token

```
POST /api/auth/logout
Authorization: Bearer {token}
```

**Curl Example:**
```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

#### List All Tokens (for current user)

```
GET /api/auth/tokens
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "api-token",
      "created_at": "2026-04-05T10:00:00Z",
      "last_used_at": "2026-04-05T10:30:00Z"
    }
  ]
}
```

#### Refresh Token

```
POST /api/auth/refresh
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "token": "2|freshnew...token"
  }
}
```

---

### Authentication Errors

| Status | Error | Cause | Solution |
|--------|-------|-------|----------|
| 401 | Invalid credentials | Wrong email/password | Verify credentials and try again |
| 401 | Unauthenticated | Missing token | Include `Authorization: Bearer {token}` header |
| 401 | Token invalid | Expired or revoked token | Generate new token via login |
| 403 | Unauthorized | Insufficient permissions | User doesn't own the resource |
| 422 | Validation error | Invalid input | Check request format |

---

## 📋 Plans Endpoints

### 1. List All Plans

```
GET /api/plans
Authorization: Bearer {token}
```

**Description:** Get all active subscription plans with pagination

**Query Parameters:**
- `page` (integer, optional, default: 1) - Page number
- `per_page` (integer, optional, default: 15, max: 100) - Results per page

**Curl Example:**
```bash
curl -X GET http://localhost:8000/api/plans?page=1&per_page=15 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Professional",
      "description": "For growing teams",
      "features": ["Advanced analytics", "API access", "Priority support"],
      "is_active": true,
      "created_at": "2026-04-05T10:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 10,
    "last_page": 1,
    "from": 1,
    "to": 10
  }
}
```

**Related Code:**
```php
// File: app/Http/Controllers/PlanController.php
public function index(Request $request): JsonResponse
{
    $page = $request->get('page', 1);
    $perPage = $request->get('per_page', 15);
    
    $plans = $this->planService->listActivePlans($page, $perPage);
    
    return response()->json([
        'success' => true,
        'data' => $plans->items(),
        'pagination' => [
            'current_page' => $plans->currentPage(),
            'per_page' => $plans->perPage(),
            'total' => $plans->total(),
            'last_page' => $plans->lastPage(),
        ],
    ]);
}
```

---

### 2. Create Plan

```
POST /api/plans
Authorization: Bearer {token}
Content-Type: application/json
```

**Description:** Create a new subscription plan

**Request Body:**
```json
{
  "name": "Professional",
  "description": "For growing teams",
  "features": ["Advanced analytics", "API access", "Priority support"]
}
```

**Validation Rules:**
```php
'name' => 'required|string|unique:plans|max:255',
'description' => 'nullable|string|max:1000',
'features' => 'nullable|json|array'
```

**Curl Example:**
```bash
curl -X POST http://localhost:8000/api/plans \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Professional",
    "description": "For growing teams",
    "features": ["Advanced analytics", "API access", "Priority support"]
  }'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Plan created successfully",
  "data": {
    "id": 1,
    "name": "Professional",
    "description": "For growing teams",
    "features": ["Advanced analytics", "API access", "Priority support"],
    "is_active": true,
    "created_at": "2026-04-05T10:00:00Z"
  }
}
```

**Related Code:**
```php
// File: app/Http/Controllers/PlanController.php
public function store(CreatePlanRequest $request): JsonResponse
{
    try {
        $plan = $this->planService->createPlan($request->validated());
        
        return response()->json([
            'success' => true,
            'message' => 'Plan created successfully',
            'data' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'features' => $plan->features,
                'is_active' => $plan->is_active,
                'created_at' => $plan->created_at->toISOString(),
            ],
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create plan',
            'error' => $e->getMessage(),
        ], 500);
    }
}

// File: app/Services/PlanService.php
public function createPlan(array $data): Plan
{
    $plan = Plan::create([
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'features' => $data['features'] ?? null,
        'is_active' => true,
    ]);
    
    return $plan;
}
```

---

### 3. Get Plan Details

```
GET /api/plans/{id}
Authorization: Bearer {token}
```

**Description:** Get detailed information about a specific plan including billing cycles and pricing

**URL Parameters:**
- `id` (integer, required) - Plan ID

**Curl Example:**
```bash
curl -X GET http://localhost:8000/api/plans/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Professional",
    "description": "For growing teams",
    "features": ["Advanced analytics", "API access"],
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
            "price": 99.99,
            "formatted_price": "$99.99"
          },
          {
            "id": 2,
            "currency": "AED",
            "price": 367.00,
            "formatted_price": "AED 367.00"
          }
        ]
      }
    ]
  }
}
```

---

### 4. Add Billing Cycle

```
POST /api/plans/{id}/billing-cycles
Authorization: Bearer {token}
Content-Type: application/json
```

**Description:** Add a billing cycle option to a plan (monthly, yearly, etc.)

**URL Parameters:**
- `id` (integer, required) - Plan ID

**Request Body:**
```json
{
  "cycle_type": "monthly",
  "duration_in_days": 30,
  "display_name": "Monthly"
}
```

**Validation Rules:**
```php
'cycle_type' => 'required|in:daily,weekly,monthly,quarterly,semi_annual,yearly',
'duration_in_days' => 'required|integer|min:1',
'display_name' => 'nullable|string|max:50'
```

**Curl Example:**
```bash
curl -X POST http://localhost:8000/api/plans/1/billing-cycles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "cycle_type": "monthly",
    "duration_in_days": 30,
    "display_name": "Monthly"
  }'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Billing cycle added successfully",
  "data": {
    "id": 1,
    "plan_id": 1,
    "cycle_type": "monthly",
    "duration_in_days": 30,
    "display_name": "Monthly"
  }
}
```

**Related Code:**
```php
// File: app/Services/PlanService.php
public function addBillingCycle(Plan $plan, string $type, int $durationDays): PlanBillingCycle
{
    return $plan->billingCycles()->create([
        'cycle_type' => $type,
        'duration_in_days' => $durationDays,
        'display_name' => ucfirst($type),
    ]);
}
```

---

### 5. Add Pricing

```
POST /api/plans/{id}/pricing
Authorization: Bearer {token}
Content-Type: application/json
```

**Description:** Add multi-currency pricing for a billing cycle

**URL Parameters:**
- `id` (integer, required) - Plan ID

**Request Body:**
```json
{
  "plan_billing_cycle_id": 1,
  "currency": "USD",
  "price": 99.99
}
```

**Validation Rules:**
```php
'plan_billing_cycle_id' => 'required|exists:plan_billing_cycles,id',
'currency' => 'required|string|size:3',
'price' => 'required|numeric|min:0.01'
```

**Curl Example:**
```bash
curl -X POST http://localhost:8000/api/plans/1/pricing \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_billing_cycle_id": 1,
    "currency": "USD",
    "price": 99.99
  }'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Pricing added successfully",
  "data": {
    "id": 1,
    "plan_billing_cycle_id": 1,
    "currency": "USD",
    "price": 99.99,
    "is_active": true
  }
}
```

**Related Code:**
```php
// File: app/Services/PlanService.php
public function addPricing(PlanBillingCycle $cycle, string $currency, float $price): PlanPrice
{
    return $cycle->prices()->create([
        'currency' => strtoupper($currency),
        'price' => $price,
        'is_active' => true,
    ]);
}
```

---

### 6. Get Plan Pricing

```
GET /api/plans/{id}/pricing?currency=USD&cycle=monthly
Authorization: Bearer {token}
```

**Description:** Get pricing for a specific currency and billing cycle

**URL Parameters:**
- `id` (integer, required) - Plan ID

**Query Parameters:**
- `currency` (string, required) - Currency code (e.g., USD, AED, EGP)
- `cycle` (string, required) - Billing cycle type (e.g., monthly, yearly)

**Curl Example:**
```bash
curl -X GET "http://localhost:8000/api/plans/1/pricing?currency=USD&cycle=monthly" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "plan_id": 1,
    "cycle_type": "monthly",
    "currency": "USD",
    "price": 99.99,
    "formatted_price": "$99.99"
  }
}
```

---

## 📋 Subscriptions Endpoints

### 1. List My Subscriptions

```
GET /api/subscriptions
Authorization: Bearer {token}
```

**Description:** Get all subscriptions for the authenticated user

**Query Parameters:**
- `page` (integer, optional, default: 1)
- `per_page` (integer, optional, default: 15)
- `status` (string, optional) - Filter by status: trialing, active, past_due, canceled

**Curl Example:**
```bash
curl -X GET "http://localhost:8000/api/subscriptions?status=active" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "plan_id": 1,
      "plan_name": "Professional",
      "status": "active",
      "trial_ends_at": null,
      "started_at": "2026-04-05T10:00:00Z",
      "current_period_start": "2026-04-05T10:00:00Z",
      "current_period_end": "2026-05-05T10:00:00Z",
      "grace_period_ends_at": null,
      "price": {
        "amount": 99.99,
        "currency": "USD",
        "billing_cycle": "monthly"
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 5
  }
}
```

---

### 2. Create Subscription

```
POST /api/subscriptions
Authorization: Bearer {token}
Content-Type: application/json
```

**Description:** Create a new subscription for the authenticated user

**Request Body:**
```json
{
  "plan_id": 1,
  "plan_billing_cycle_id": 1,
  "currency": "USD",
  "trial_period_days": 14
}
```

**Validation Rules:**
```php
'plan_id' => 'required|exists:plans,id',
'plan_billing_cycle_id' => 'required|exists:plan_billing_cycles,id',
'currency' => 'required|string|size:3|exists:plan_prices,currency',
'trial_period_days' => 'nullable|integer|min:0|max:365'
```

**Curl Example:**
```bash
curl -X POST http://localhost:8000/api/subscriptions \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_id": 1,
    "plan_billing_cycle_id": 1,
    "currency": "USD",
    "trial_period_days": 14
  }'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Subscription created successfully",
  "data": {
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
}
```

**Related Code:**
```php
// File: app/Services/SubscriptionService.php
public function createSubscription(
    User $user,
    Plan $plan,
    PlanBillingCycle $cycle,
    string $currency,
    bool $withTrial = false,
    int $trialDays = 14
): Subscription {
    $planPrice = $plan->getPricing($currency, $cycle);
    
    $subscription = $user->subscriptions()->create([
        'plan_id' => $plan->id,
        'plan_billing_cycle_id' => $cycle->id,
        'plan_price_id' => $planPrice->id,
        'status' => SubscriptionState::TRIALING,
        'trial_ends_at' => $withTrial ? Carbon::now('UTC')->addDays($trialDays) : null,
        'started_at' => Carbon::now('UTC'),
        'current_period_start' => Carbon::now('UTC'),
        'current_period_end' => Carbon::now('UTC')->addDays($cycle->duration_in_days),
    ]);
    
    SubscriptionHistory::create([
        'subscription_id' => $subscription->id,
        'new_status' => SubscriptionState::TRIALING,
        'reason' => 'Subscription created',
    ]);
    
    return $subscription;
}
```

---

### 3. Get Subscription Details

```
GET /api/subscriptions/{id}
Authorization: Bearer {token}
```

**Description:** Get detailed information about a specific subscription

**URL Parameters:**
- `id` (integer, required) - Subscription ID

**Curl Example:**
```bash
curl -X GET http://localhost:8000/api/subscriptions/5 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 5,
    "user_id": 123,
    "plan_id": 1,
    "plan_name": "Professional",
    "status": "active",
    "trial_ends_at": null,
    "started_at": "2026-04-05T10:00:00Z",
    "current_period_start": "2026-04-05T10:00:00Z",
    "current_period_end": "2026-05-05T10:00:00Z",
    "grace_period_ends_at": null,
    "canceled_at": null,
    "price": {
      "id": 1,
      "amount": 99.99,
      "currency": "USD",
      "billing_cycle": "monthly"
    },
    "plan": {
      "id": 1,
      "name": "Professional",
      "features": ["Advanced analytics", "API access"]
    },
    "created_at": "2026-04-05T10:00:00Z"
  }
}
```

---

### 4. Get Subscription Status Info

```
GET /api/subscriptions/{id}/status-info
Authorization: Bearer {token}
```

**Description:** Get subscription status with transition information and capabilities

**URL Parameters:**
- `id` (integer, required) - Subscription ID

**Curl Example:**
```bash
curl -X GET http://localhost:8000/api/subscriptions/5/status-info \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "current_status": "active",
    "status_label": "Active",
    "can_activate": false,
    "can_mark_past_due": false,
    "can_recover": false,
    "can_cancel": true,
    "valid_transitions": ["past_due", "canceled"],
    "is_in_grace_period": false,
    "grace_remaining_days": 0
  }
}
```

**Related Code:**
```php
// File: app/Services/SubscriptionStateService.php
public function getStatusInfo(Subscription $subscription): array
{
    return [
        'current_status' => $subscription->status->value,
        'status_label' => $subscription->status->getLabel(),
        'can_activate' => $this->canTransitionTo($subscription, SubscriptionState::ACTIVE),
        'can_mark_past_due' => $this->canTransitionTo($subscription, SubscriptionState::PAST_DUE),
        'can_recover' => $this->canTransitionTo($subscription, SubscriptionState::ACTIVE) && 
                         $this->gracePeriodService->isInGracePeriod($subscription),
        'can_cancel' => $this->canTransitionTo($subscription, SubscriptionState::CANCELED),
        'valid_transitions' => array_map(fn($state) => $state->value, 
                                         $this->getValidTransitions($subscription)),
        'is_in_grace_period' => $this->gracePeriodService->isInGracePeriod($subscription),
        'grace_remaining_days' => $this->gracePeriodService->getGraceRemainingDays($subscription),
    ];
}
```

---

### 5. Cancel Subscription

```
POST /api/subscriptions/{id}/cancel
Authorization: Bearer {token}
Content-Type: application/json
```

**Description:** Cancel an active or trialing subscription

**URL Parameters:**
- `id` (integer, required) - Subscription ID

**Request Body:**
```json
{
  "reason": "User requested cancellation"
}
```

**Curl Example:**
```bash
curl -X POST http://localhost:8000/api/subscriptions/5/cancel \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "User requested cancellation"
  }'
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Subscription canceled successfully",
  "data": {
    "id": 5,
    "status": "canceled",
    "canceled_at": "2026-04-05T10:30:00Z",
    "cancellation_reason": "User requested cancellation"
  }
}
```

**Related Code:**
```php
// File: app/Services/SubscriptionStateService.php
public function cancel(Subscription $subscription, string $reason = 'User requested'): Subscription
{
    return DB::transaction(function () use ($subscription, $reason) {
        $subscription->update([
            'canceled_at' => Carbon::now('UTC'),
            'cancellation_reason' => $reason,
            'ends_at' => $subscription->current_period_end,
        ]);
        
        return $this->transitionTo($subscription, SubscriptionState::CANCELED, $reason);
    });
}
```

---

### 6. Get Subscription History

```
GET /api/subscriptions/{id}/history
Authorization: Bearer {token}
```

**Description:** Get audit trail of all state changes for a subscription

**URL Parameters:**
- `id` (integer, required) - Subscription ID

**Curl Example:**
```bash
curl -X GET http://localhost:8000/api/subscriptions/5/history \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "subscription_id": 5,
      "previous_status": null,
      "new_status": "trialing",
      "reason": "Subscription created",
      "metadata": null,
      "created_at": "2026-04-05T10:00:00Z"
    },
    {
      "id": 2,
      "subscription_id": 5,
      "previous_status": "trialing",
      "new_status": "active",
      "reason": "Trial ended, billing started",
      "metadata": null,
      "created_at": "2026-04-19T00:01:00Z"
    }
  ]
}
```

---

### 7. Change Billing Cycle

```
PUT /api/subscriptions/{id}/billing-cycle
Authorization: Bearer {token}
Content-Type: application/json
```

**Description:** Change subscription billing cycle (e.g., monthly to yearly)

**URL Parameters:**
- `id` (integer, required) - Subscription ID

**Request Body:**
```json
{
  "plan_billing_cycle_id": 2
}
```

**Curl Example:**
```bash
curl -X PUT http://localhost:8000/api/subscriptions/5/billing-cycle \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_billing_cycle_id": 2
  }'
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Billing cycle changed successfully",
  "data": {
    "id": 5,
    "plan_billing_cycle_id": 2,
    "current_period_end": "2027-04-05T10:00:00Z"
  }
}
```

---

## 📡 Webhooks Endpoints

### 1. Payment Success Webhook

```
POST /api/webhooks/payment-success
Content-Type: application/json
X-Webhook-Signature: {signature}
```

**Description:** Handle successful payment from payment provider (PUBLIC - no auth required)

**Request Body:**
```json
{
  "subscription_id": 5,
  "amount": 99.99,
  "currency": "USD",
  "status": "completed",
  "transaction_id": "txn_1234567890"
}
```

**Curl Example:**
```bash
curl -X POST http://localhost:8000/api/webhooks/payment-success \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: your-signature" \
  -d '{
    "subscription_id": 5,
    "amount": 99.99,
    "currency": "USD",
    "status": "completed",
    "transaction_id": "txn_1234567890"
  }'
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Payment processed successfully",
  "data": {
    "subscription_id": 5,
    "new_status": "active"
  }
}
```

**Related Code:**
```php
// File: app/Http/Controllers/WebhookController.php
public function paymentSuccess(Request $request): JsonResponse
{
    $subscription = Subscription::findOrFail($request->subscription_id);
    
    try {
        $this->paymentService->handlePaymentSuccess($subscription);
        
        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully',
        ]);
    } catch (\Exception $e) {
        Log::error('Payment success webhook failed', ['error' => $e->getMessage()]);
        return response()->json(['success' => false], 400);
    }
}
```

---

### 2. Payment Failed Webhook

```
POST /api/webhooks/payment-failed
Content-Type: application/json
X-Webhook-Signature: {signature}
```

**Description:** Handle failed payment - subscription enters grace period (PUBLIC - no auth required)

**Request Body:**
```json
{
  "subscription_id": 5,
  "amount": 99.99,
  "currency": "USD",
  "reason": "card_declined",
  "error_code": "card_declined",
  "error_message": "Your card was declined.",
  "transaction_id": "txn_1234567890"
}
```

**Curl Example:**
```bash
curl -X POST http://localhost:8000/api/webhooks/payment-failed \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: your-signature" \
  -d '{
    "subscription_id": 5,
    "amount": 99.99,
    "currency": "USD",
    "reason": "card_declined",
    "error_code": "card_declined",
    "error_message": "Your card was declined.",
    "transaction_id": "txn_1234567890"
  }'
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Payment failure recorded",
  "data": {
    "subscription_id": 5,
    "new_status": "past_due",
    "grace_period_ends_at": "2026-04-08T10:00:00Z"
  }
}
```

**Related Code:**
```php
// File: app/Http/Controllers/WebhookController.php
public function paymentFailed(Request $request): JsonResponse
{
    $subscription = Subscription::findOrFail($request->subscription_id);
    
    try {
        $this->paymentService->handlePaymentFailure(
            $subscription,
            $request->reason,
            $request->error_code
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Payment failure recorded',
        ]);
    } catch (\Exception $e) {
        Log::error('Payment failed webhook error', ['error' => $e->getMessage()]);
        return response()->json(['success' => false], 400);
    }
}
```

---

### 3. Payment Recovered Webhook

```
POST /api/webhooks/payment-recovered
Content-Type: application/json
X-Webhook-Signature: {signature}
```

**Description:** Handle payment recovery during grace period (PUBLIC - no auth required)

**Request Body:**
```json
{
  "subscription_id": 5,
  "amount": 99.99,
  "currency": "USD",
  "recovery_type": "retry",
  "transaction_id": "txn_0987654321"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Subscription recovered",
  "data": {
    "subscription_id": 5,
    "new_status": "active"
  }
}
```

---

## 📋 Response Format

### Success Response

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { /* ... */ }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  },
  "code": "ERROR_CODE"
}
```

### Validation Error (422)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "plan_id": ["The plan id field is required."],
    "currency": ["The currency must be 3 characters."]
  }
}
```

---

## 🚨 Error Handling

### Common HTTP Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| 200 | OK | GET successful, PUT/POST completed |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid input, validation failed |
| 401 | Unauthorized | Missing or invalid token |
| 403 | Forbidden | User not authorized for resource |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation error |
| 500 | Server Error | Internal server error |

### Exception Handling

```php
// File: app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($request->expectsJson()) {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $exception->errors(),
            ], 422);
        }
        
        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
            ], 404);
        }
    }
    
    return parent::render($request, $exception);
}
```

---

## 📝 Code Examples

### PHP/Laravel Client

```php
use Illuminate\Support\Facades\Http;

// Create subscription
$response = Http::withToken($token)
    ->post('http://localhost:8000/api/subscriptions', [
        'plan_id' => 1,
        'plan_billing_cycle_id' => 1,
        'currency' => 'USD',
        'trial_period_days' => 14,
    ]);

if ($response->successful()) {
    $subscription = $response->json('data');
    echo "Created subscription: " . $subscription['id'];
} else {
    echo "Error: " . $response->json('message');
}
```

### JavaScript/Fetch

```javascript
// Get subscriptions
const token = 'YOUR_API_TOKEN';

fetch('http://localhost:8000/api/subscriptions', {
    method: 'GET',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Subscriptions:', data.data);
    } else {
        console.error('Error:', data.message);
    }
});
```

### Python/Requests

```python
import requests

TOKEN = 'YOUR_API_TOKEN'
BASE_URL = 'http://localhost:8000/api/v1'

headers = {
    'Authorization': f'Bearer {TOKEN}',
    'Content-Type': 'application/json'
}

# Create plan
response = requests.post(
    f'{BASE_URL}/plans',
    headers=headers,
    json={
        'name': 'Professional',
        'description': 'For growing teams',
        'features': ['Advanced analytics', 'API access']
    }
)

if response.status_code == 201:
    plan = response.json()['data']
    print(f"Created plan: {plan['id']}")
else:
    print(f"Error: {response.json()['message']}")
```

---

## 📚 Related Files

- [README.md](README.md) - Quick start guide
- [Postman-Collection.json](Postman-Collection.json) - Import in Postman for testing
- [app/Http/Controllers/PlanController.php](app/Http/Controllers/PlanController.php) - Plan endpoints
- [app/Http/Controllers/SubscriptionController.php](app/Http/Controllers/SubscriptionController.php) - Subscription endpoints
- [app/Http/Controllers/WebhookController.php](app/Http/Controllers/WebhookController.php) - Webhook endpoints
- [routes/api.php](routes/api.php) - Route definitions

---

**Last Updated:** April 5, 2026
**Version:** 1.0
