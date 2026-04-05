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
        Schema::create('failed_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('failure_reason');
            $table->string('provider_error_code')->nullable();
            $table->text('provider_error_message')->nullable();
            $table->timestamp('failed_at');
            $table->boolean('recovered')->default(false);
            $table->timestamp('recovered_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('subscription_id');
            $table->index('failed_at');
            $table->index('recovered');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_payments');
    }
};
