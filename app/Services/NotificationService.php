<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationService
{
    // ─────────────────────────────────────────────
    // Notification Delivery
    // ─────────────────────────────────────────────

    /**
     * Send a notification respecting user preferences.
     */
    public function send(User $user, string $category, array $data): void
    {
        DB::transaction(function () use ($user, $category, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $eventKey = hash('sha256', $user->id.':'.($data['event_key'] ?? (string) Str::uuid()));
            if (DB::table('notifications')->where('event_key', $eventKey)->exists()) {
                return;
            }
            unset($data['event_key']);
            DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'event_key' => $eventKey,
                'type' => $data['type'] ?? 'generic', 'notifiable_type' => User::class, 'notifiable_id' => $user->id,
                'data' => json_encode([...$data, 'category' => $category, 'channels' => ['database']]),
                'read_at' => null, 'created_at' => now(), 'updated_at' => now()]);
            app(NotificationDeliveryService::class)->enqueue($user, $category, $data, $eventKey);
        });
    }

    /**
     * Store notification in the database (uses Laravel's notification table).
     */
    public function storeNotification(User $user, string $category, array $data, array $channels = ['database']): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => $data['type'] ?? 'App\\Notifications\\GenericNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $user->id,
            'data' => json_encode(array_merge($data, [
                'category' => $category,
                'channels' => $channels,
            ])),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─────────────────────────────────────────────
    // Bulk / Specific Notification Senders
    // ─────────────────────────────────────────────

    public function sendLearningReminder(User $user): void
    {
        $this->send($user, 'learning', [
            'type' => 'learning_reminder',
            'title' => 'Time to Study!',
            'body' => 'Continue your learning journey. Your daily study session awaits.',
            'action_url' => '/dashboard',
        ]);
    }

    public function sendStreakRescue(User $user, int $currentStreak): void
    {
        $this->send($user, 'learning', [
            'type' => 'streak_rescue',
            'title' => 'Don\'t Lose Your Streak! 🔥',
            'body' => "You have a {$currentStreak}-day streak. Complete one activity to keep it going!",
            'action_url' => '/dashboard',
        ]);
    }

    public function sendEnrollmentConfirmation(User $user, string $courseName, string $orderUuid): void
    {
        $this->send($user, 'system', [
            'type' => 'enrollment_confirmation', 'event_key' => 'enrollment:'.$orderUuid.':'.$courseName,
            'title' => 'Enrollment Confirmed',
            'body' => "You've been enrolled in {$courseName}. Start learning now!",
            'action_url' => '/courses',
        ]);
    }

    public function sendPaymentReceipt(User $user, string $orderUuid, float $amount, string $currency): void
    {
        $this->send($user, 'system', [
            'type' => 'payment_receipt', 'event_key' => 'receipt:'.$orderUuid,
            'title' => 'Payment Received',
            'body' => "Your payment of {$currency} ".number_format($amount, 2).' has been confirmed.',
            'action_url' => "/orders/{$orderUuid}",
        ]);
    }

    public function sendCertificateIssued(User $user, string $certificateTitle, string $uuid): void
    {
        $this->send($user, 'system', [
            'type' => 'certificate_issued', 'event_key' => 'certificate:'.$uuid,
            'title' => 'Certificate Earned! 🎉',
            'body' => "Your certificate for {$certificateTitle} is ready.",
            'action_url' => "/certificates/{$uuid}",
        ]);
    }

    public function sendInternshipReminder(User $user, string $taskTitle, string $dueDate): void
    {
        $this->send($user, 'internship', [
            'type' => 'internship_task_due',
            'title' => 'Task Due Soon',
            'body' => "\"{$taskTitle}\" is due on {$dueDate}.",
            'action_url' => '/internships',
        ]);
    }

    public function sendSponsorFeedback(User $user, string $submissionTitle): void
    {
        $this->send($user, 'internship', [
            'type' => 'sponsor_feedback',
            'title' => 'Feedback Received',
            'body' => "Your sponsor has reviewed \"{$submissionTitle}\".",
            'action_url' => '/internships',
        ]);
    }

    public function sendMeetingReminder(User $user, string $meetingTitle, string $startsAt): void
    {
        $this->send($user, 'internship', [
            'type' => 'meeting_reminder',
            'title' => 'Meeting Starting Soon',
            'body' => "\"{$meetingTitle}\" starts at {$startsAt}.",
            'action_url' => '/meetings',
        ]);
    }

    public function sendAnnouncement(User $user, string $title, string $message): void
    {
        $this->send($user, 'admin', [
            'type' => 'announcement',
            'title' => $title,
            'body' => $message,
            'action_url' => '/dashboard',
        ]);
    }

    // ─────────────────────────────────────────────
    // Notification Management
    // ─────────────────────────────────────────────

    public function getNotifications(User $user, ?string $category = null, ?bool $unreadOnly = false, int $perPage = 20)
    {
        $query = DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $user->id)
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        if ($category) {
            $query->whereRaw("JSON_EXTRACT(data, '$.category') = ?", [$category]);
        }

        return $query->paginate($perPage);
    }

    public function markAsRead(string $notificationId, User $user): bool
    {
        return DB::table('notifications')
            ->where('id', $notificationId)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]) > 0;
    }

    public function markAllAsRead(User $user): int
    {
        return DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function getUnreadCount(User $user): int
    {
        return DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    // ─────────────────────────────────────────────
    // Preferences
    // ─────────────────────────────────────────────

    public function getPreferences(User $user): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'push_enabled' => true,
                'email_enabled' => true,
                'learning_reminders' => true,
                'internship_reminders' => true,
                'announcements' => true,
                'frequency' => 'realtime',
            ]
        );
    }

    public function updatePreferences(User $user, array $data): NotificationPreference
    {
        $prefs = $this->getPreferences($user);
        $prefs->update($data);

        return $prefs->fresh();
    }

    // ─────────────────────────────────────────────
    // Digest (queue for later)
    // ─────────────────────────────────────────────

}
