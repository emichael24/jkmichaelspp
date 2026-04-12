<?php

namespace App\Http\Controllers;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    /**
     * List conversations.
     */
    public function index(): Response
    {
        $userId = auth()->id();
        $conversations = DirectMessage::conversationsFor($userId);

        // Load user details for each conversation partner
        $partnerIds = $conversations->keys()->toArray();
        $partners = User::whereIn('id', $partnerIds)
            ->select('id', 'first_name', 'last_name', 'avatar')
            ->get()
            ->keyBy('id');

        $conversationList = $conversations->map(function ($lastMessage, $partnerId) use ($partners, $userId) {
            $partner = $partners[$partnerId] ?? null;
            return [
                'partner' => $partner,
                'last_message' => $lastMessage,
                'unread_count' => DirectMessage::where('sender_id', $partnerId)
                    ->where('recipient_id', $userId)
                    ->whereNull('read_at')
                    ->count(),
            ];
        })->values();

        return Inertia::render('Messages/Index', [
            'conversations' => $conversationList,
            'unreadTotal' => DirectMessage::unreadCountFor($userId),
        ]);
    }

    /**
     * Show conversation with a specific user.
     */
    public function show(int $partnerId): Response
    {
        $userId = auth()->id();
        $partner = User::select('id', 'first_name', 'last_name', 'avatar')->findOrFail($partnerId);

        $messages = DirectMessage::conversation($userId, $partnerId)->get();

        // Mark received messages as read
        DirectMessage::where('sender_id', $partnerId)
            ->where('recipient_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return Inertia::render('Messages/Conversation', [
            'partner' => $partner,
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'body' => 'required|string|max:5000',
            'attachment_url' => 'nullable|string|max:500',
        ]);

        DirectMessage::create([
            'sender_id' => auth()->id(),
            'recipient_id' => $validated['recipient_id'],
            'body' => $validated['body'],
            'attachment_url' => $validated['attachment_url'] ?? null,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Message sent.');
    }

    /**
     * Search users to start a new conversation.
     */
    public function searchUsers(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $users = User::where('id', '!=', auth()->id())
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'LIKE', "%{$query}%")
                    ->orWhere('last_name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->select('id', 'first_name', 'last_name', 'avatar')
            ->limit(10)
            ->get();

        return response()->json($users);
    }

    /**
     * Get unread count (for notification badge).
     */
    public function unreadCount()
    {
        return response()->json([
            'count' => DirectMessage::unreadCountFor(auth()->id()),
        ]);
    }
}
