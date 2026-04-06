<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionState;
use App\Models\Subscription;
use App\Services\GracePeriodService;
use App\Services\SubscriptionStateService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessGracePeriodCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process-grace-period {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process subscriptions that have exceeded their grace period';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $now = Carbon::now('UTC');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info("🔄 Processing expired grace periods at {$now->toISOString()}");

        // Find subscriptions in past_due status with expired grace periods
        $expiredGracePeriods = Subscription::where('status', SubscriptionState::PAST_DUE)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', $now)
            ->where('updated_at', '<', $now->copy()->subHour()) // Idempotency check
            ->lockForUpdate()
            ->get();

        if ($expiredGracePeriods->isEmpty()) {
            $this->info('✅ No expired grace periods found');
            return self::SUCCESS;
        }

        $this->info("📋 Found {$expiredGracePeriods->count()} subscription(s) with expired grace period(s) to process");

        $processed = 0;
        $successful = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar($expiredGracePeriods->count());
        $progressBar->start();

        foreach ($expiredGracePeriods as $subscription) {
            try {
                DB::beginTransaction();

                if (!$dryRun) {
                    // Use the state service to cancel the subscription
                    $stateService = app(SubscriptionStateService::class);
                    $cancelledSubscription = $stateService->cancel(
                        $subscription,
                        'Grace period expired'
                    );

                    // Log the cancellation
                    Log::info("Grace period expired, subscription cancelled", [
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'plan_name' => $subscription->plan->name,
                        'grace_period_ended_at' => $subscription->grace_period_ends_at->toISOString(),
                        'cancelled_at' => $now->toISOString(),
                    ]);

                    // Here you could send a final cancellation email
                    // Example: Notification::send($subscription->user, new SubscriptionCancelledNotification($subscription));
                }

                DB::commit();
                $successful++;
                $processed++;

            } catch (\Exception $e) {
                DB::rollBack();

                $failed++;
                Log::error("Failed to cancel subscription {$subscription->id} after grace period expiry", [
                    'error' => $e->getMessage(),
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'grace_period_ends_at' => $subscription->grace_period_ends_at?->toISOString(),
                ]);

                $this->error("Failed to process subscription {$subscription->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("📊 Processing complete:");
        $this->info("   • Processed: {$processed}");
        $this->info("   • Successful: {$successful}");
        $this->info("   • Failed: {$failed}");

        if ($dryRun) {
            $this->warn("🔍 This was a dry run - no changes were made");
        }

        // Send grace period warnings for subscriptions approaching expiry
        $this->sendGracePeriodWarnings();

        // Log summary
        Log::info("ProcessGracePeriodCommand completed", [
            'processed' => $processed,
            'successful' => $successful,
            'failed' => $failed,
            'dry_run' => $dryRun,
            'timestamp' => $now->toISOString(),
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Send warnings to users whose grace period is about to expire
     */
    private function sendGracePeriodWarnings(): void
    {
        $gracePeriodService = app(GracePeriodService::class);

        try {
            $subscriptions = $gracePeriodService->getSubscriptionsApproachingExpiry(24); // 24 hours

            if ($subscriptions->isNotEmpty()) {
                $this->info("📧 Sending grace period warnings to {$subscriptions->count()} user(s)");

                foreach ($subscriptions as $subscription) {
                    // Here you would integrate with your notification system
                    Log::info("Grace period warning sent", [
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'plan_name' => $subscription->plan->name,
                        'grace_period_ends_at' => $subscription->grace_period_ends_at->toISOString(),
                        'remaining_hours' => $gracePeriodService->getGraceRemainingHours($subscription),
                    ]);

                    // Example notification:
                    // Notification::send($subscription->user, new GracePeriodExpiringWarning($subscription));
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to send grace period warnings", [
                'error' => $e->getMessage(),
            ]);
            $this->error("Failed to send grace period warnings: {$e->getMessage()}");
        }
    }
}
