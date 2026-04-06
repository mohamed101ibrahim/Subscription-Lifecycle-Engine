<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected authentication routes
Route::prefix('auth')->middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('tokens', [AuthController::class, 'tokens']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

// Public webhook routes (no authentication required)
Route::prefix('webhooks')->group(function () {
    Route::post('payment-success', [WebhookController::class, 'paymentSuccess']);
    Route::post('payment-failed', [WebhookController::class, 'paymentFailed']);
    Route::post('payment-recovered', [WebhookController::class, 'paymentRecovered']);
    Route::post('subscription-cancelled', [WebhookController::class, 'subscriptionCancelled']);
    Route::post('{provider}', [WebhookController::class, 'genericWebhook']);
});

// Protected API routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {

    // Plan management routes
    Route::prefix('plans')->group(function () {
        Route::get('/', [PlanController::class, 'index']);
        Route::post('/', [PlanController::class, 'store']);
        Route::get('{plan}', [PlanController::class, 'show']);
        Route::put('{plan}', [PlanController::class, 'update']);
        Route::delete('{plan}', [PlanController::class, 'destroy']);
        Route::post('{plan}/billing-cycles', [PlanController::class, 'addBillingCycle']);
        Route::post('{plan}/pricing', [PlanController::class, 'addPricing']);
        Route::get('{plan}/pricing', [PlanController::class, 'getPricing']);
    });

    // Subscription management routes
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::post('/', [SubscriptionController::class, 'store']);
        Route::get('{subscription}', [SubscriptionController::class, 'show']);
        Route::put('{subscription}', [SubscriptionController::class, 'update']);
        Route::delete('{subscription}', [SubscriptionController::class, 'destroy']);
        Route::post('{subscription}/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('{subscription}/retry-payment', [SubscriptionController::class, 'retryPayment']);
        Route::put('{subscription}/billing-cycle', [SubscriptionController::class, 'changeBillingCycle']);
        Route::get('{subscription}/history', [SubscriptionController::class, 'history']);
        Route::get('{subscription}/status-info', [SubscriptionController::class, 'statusInfo']);
    });

});
