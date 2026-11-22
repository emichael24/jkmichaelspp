<?php

use App\Jobs\OperationalHeartbeat;
use App\Models\MessagingCampaign;
use App\Models\PaymentRefund;
use App\Models\QuizAttempt;
use App\Services\AssessmentAttemptService;
use App\Services\CampaignDeliveryService;
use App\Services\NotificationDeliveryService;
use App\Services\PaymentRefundService;
use App\Services\SubscriptionBillingService;
use App\Services\UploadRetentionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    Cache::put('operations:scheduler-heartbeat', now()->timestamp, 600);
    OperationalHeartbeat::dispatch();
})->everyMinute()->name('operational-heartbeat')->withoutOverlapping();

Artisan::command('billing:reconcile', function () {
    $count = app(SubscriptionBillingService::class)->retryPending();
    $this->info("Rechecked {$count} pending provider events.");
});
Schedule::command('billing:reconcile')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('billing:reconcile-refunds', function () {
    $failures = 0;
    foreach (PaymentRefund::whereNotIn('status', ['requested', 'processed', 'failed'])->orderBy('reconciled_at')->limit(50)->get() as $refund) {
        try {
            app(PaymentRefundService::class)->reconcile($refund);
        } catch (Throwable $error) {
            $failures++;
            report($error);
        }
    }
    $this->info('Unconfirmed refunds: '.$failures);

    return $failures ? 1 : 0;
});
Schedule::command('billing:reconcile-refunds')->everyTenMinutes()->withoutOverlapping();

Artisan::command('notifications:dispatch', function () {
    foreach (MessagingCampaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->limit(50)->get() as $campaign) {
        try {
            app(CampaignDeliveryService::class)->launch($campaign);
        } catch (Throwable $error) {
            $campaign->update(['status' => 'paused']);
            report(new RuntimeException('Scheduled campaign '.$campaign->id.' could not launch ('.class_basename($error).').'));
        }
    }
    $count = app(NotificationDeliveryService::class)->dispatchDue();
    $this->info("Queued {$count} pending deliveries.");
});
Schedule::command('notifications:dispatch')->everyMinute()->withoutOverlapping();

Artisan::command('assessments:expire', function () {
    $completed = 0;
    QuizAttempt::where('status', 'in_progress')->whereNull('paused_at')->where('expires_at', '<', now())
        ->orderBy('id')->chunkById(100, function ($attempts) use (&$completed) {
            foreach ($attempts as $attempt) {
                try {
                    if (empty($attempt->question_snapshot)) {
                        $attempt->update(['status' => 'abandoned']);

                        continue;
                    }
                    app(AssessmentAttemptService::class)->complete($attempt);
                    $completed++;
                } catch (Throwable $error) {
                    report($error);
                }
            }
        });
    $this->info("Completed {$completed} expired attempts using saved answers.");
});
Schedule::command('assessments:expire')->everyMinute()->withoutOverlapping();

Schedule::call(fn () => DB::table('offline_progress_events')->where('created_at', '<', now()->subDays(30))->delete())
    ->daily()->name('offline-event-retention')->withoutOverlapping();

Artisan::command('media:cleanup-uploads', function () {
    $this->info(json_encode(app(UploadRetentionService::class)->cleanup(), JSON_THROW_ON_ERROR));
})->purpose('Remove upload fragments idle for 24 hours without interrupting locked uploads');
Schedule::command('media:cleanup-uploads')->hourly()->withoutOverlapping();
Schedule::command('crm:cleanup-imports')->daily()->withoutOverlapping();
