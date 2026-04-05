<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans');
            $table->foreignId('plan_billing_cycle_id')->constrained('plan_billing_cycles');
            $table->foreignId('plan_price_id')->constrained('plan_prices');
            
            // Status
            $table->enum('status', ['trialing', 'active', 'past_due', 'canceled'])->default('trialing');
            
            // Trial Information
            $table->timestamp('trial_ends_at')->nullable();
            
            // Subscription Period
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            
            // Grace Period
            $table->timestamp('grace_period_ends_at')->nullable();
            
            // Cancellation
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('trial_ends_at');
            $table->index('grace_period_ends_at');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
