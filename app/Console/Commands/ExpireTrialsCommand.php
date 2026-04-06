<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionState;
use App\Models\Subscription;
use App\Services\SubscriptionStateService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireTrialsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:expire-trials {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire trial subscriptions that have reached their end date';

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

        $this->info("🔄 Processing expired trials at {$now->toISOString()}");

        // Find subscriptions that are trialing and have expired
        $expiredTrials = Subscription::where('status', SubscriptionState::TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now)
            ->where('updated_at', '<', $now->copy()->subHour()) // Idempotency check
            ->lockForUpdate()
            ->get();

        if ($expiredTrials->isEmpty()) {
            $this->info('✅ No expired trials found');
            return self::SUCCESS;
        }

        $this->info("📋 Found {$expiredTrials->count()} expired trial(s) to process");

        $processed = 0;
        $successful = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar($expiredTrials->count());
        $progressBar->start();

        foreach ($expiredTrials as $subscription) {
            try {
                DB::beginTransaction();

                if (!$dryRun) {
                    // Use the state service to cancel the subscription
                    $stateService = app(SubscriptionStateService::class);
                    $canceledSubscription = $stateService->cancel($subscription, 'Trial period expired');

                    // Log the cancellation
                    Log::info("Trial expired and subscription canceled", [
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'plan_name' => $subscription->plan->name,
                        'trial_ended_at' => $subscription->trial_ends_at->toISOString(),
                        'canceled_at' => $now->toISOString(),
                    ]);

                    // Here you could send an email notification
                    // Example: Notification::send($subscription->user, new TrialExpiredNotification($subscription));
                }

                DB::commit();
                $successful++;
                $processed++;

            } catch (\Exception $e) {
                DB::rollBack();

                $failed++;
                Log::error("Failed to expire trial for subscription {$subscription->id}", [
                    'error' => $e->getMessage(),
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
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

        // Log summary
        Log::info("ExpireTrialsCommand completed", [
            'processed' => $processed,
            'successful' => $successful,
            'failed' => $failed,
            'dry_run' => $dryRun,
            'timestamp' => $now->toISOString(),
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
