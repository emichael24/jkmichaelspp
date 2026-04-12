<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * Notification list page.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $category = $request->category;
        $unreadOnly = $request->boolean('unread');

        $notifications = $this->notificationService->getNotifications($user, $category, $unreadOnly);
        $unreadCount = $this->notificationService->getUnreadCount($user);

        // Transform for frontend
        $notifications->getCollection()->transform(function ($n) {
            $data = json_decode($n->data, true);
            return [
                'id' => $n->id,
                'type' => $data['type'] ?? 'generic',
                'title' => $data['title'] ?? '',
                'body' => $data['body'] ?? '',
                'action_url' => $data['action_url'] ?? null,
                'category' => $data['category'] ?? 'system',
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ];
        });

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'filters' => $request->only('category', 'unread'),
        ]);
    }

    /**
     * Mark single notification as read.
     */
    public function markRead(string $id)
    {
        $this->notificationService->markAsRead($id, auth()->user());
        return back();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead()
    {
        $count = $this->notificationService->markAllAsRead(auth()->user());
        return back()->with('success', "{$count} notifications marked as read.");
    }

    /**
     * Notification preferences page.
     */
    public function preferences()
    {
        $prefs = $this->notificationService->getPreferences(auth()->user());

        return Inertia::render('Notifications/Preferences', [
            'preferences' => $prefs,
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(Request $request)
    {
        $request->validate([
            'push_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i',
            'learning_reminders' => 'boolean',
            'internship_reminders' => 'boolean',
            'announcements' => 'boolean',
            'frequency' => 'in:realtime,daily_digest,weekly_digest',
        ]);

        $this->notificationService->updatePreferences(auth()->user(), $request->only([
            'push_enabled', 'email_enabled', 'quiet_hours_start', 'quiet_hours_end',
            'learning_reminders', 'internship_reminders', 'announcements', 'frequency',
        ]));

        return back()->with('success', 'Preferences updated.');
    }

    /**
     * Get unread count (JSON, for badge).
     */
    public function unreadCount()
    {
        return response()->json([
            'count' => $this->notificationService->getUnreadCount(auth()->user()),
        ]);
    }
}
