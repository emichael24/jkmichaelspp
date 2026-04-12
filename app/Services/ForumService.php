<?php

namespace App\Services;

use App\Models\Forum;
use App\Models\ForumThread;
use App\Models\ForumReply;
use Illuminate\Pagination\LengthAwarePaginator;

class ForumService
{
    /**
     * Get forums for a user (general + enrolled course forums + cohort forums).
     */
    public function getForumsForUser(int $userId): array
    {
        $general = Forum::general()
            ->withCount('threads')
            ->orderBy('sort_order')
            ->get();

        $courseForums = Forum::whereNotNull('course_id')
            ->whereHas('course', function ($q) use ($userId) {
                $q->whereHas('enrollments', fn($e) => $e->where('user_id', $userId));
            })
            ->with('course:id,title')
            ->withCount('threads')
            ->orderBy('sort_order')
            ->get();

        $cohortForums = Forum::whereNotNull('cohort_id')
            ->whereHas('cohort', function ($q) use ($userId) {
                $q->whereHas('members', fn($m) => $m->where('user_id', $userId));
            })
            ->with('cohort:id,name')
            ->withCount('threads')
            ->orderBy('sort_order')
            ->get();

        return [
            'general' => $general,
            'courses' => $courseForums,
            'cohorts' => $cohortForums,
        ];
    }

    /**
     * Get threads for a forum with pagination.
     */
    public function getThreads(int $forumId, int $perPage = 20): LengthAwarePaginator
    {
        return ForumThread::where('forum_id', $forumId)
            ->with(['user:id,first_name,last_name,avatar'])
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Create a new thread.
     */
    public function createThread(int $forumId, int $userId, array $data): ForumThread
    {
        return ForumThread::create([
            'forum_id' => $forumId,
            'user_id' => $userId,
            'title' => $data['title'],
            'body' => $data['body'],
        ]);
    }

    /**
     * Get a thread with replies.
     */
    public function getThread(int $threadId): ForumThread
    {
        return ForumThread::with([
            'user:id,first_name,last_name,avatar',
            'forum:id,title,course_id,cohort_id',
            'replies' => function ($q) {
                $q->whereNull('parent_id')
                    ->with([
                        'user:id,first_name,last_name,avatar',
                        'children.user:id,first_name,last_name,avatar',
                    ])
                    ->orderBy('is_accepted_answer', 'desc')
                    ->orderBy('created_at');
            },
        ])->findOrFail($threadId);
    }

    /**
     * Create a reply to a thread.
     */
    public function createReply(int $threadId, int $userId, array $data): ForumReply
    {
        $thread = ForumThread::findOrFail($threadId);

        if ($thread->is_locked) {
            throw new \Exception('This thread is locked.');
        }

        $reply = ForumReply::create([
            'thread_id' => $threadId,
            'user_id' => $userId,
            'body' => $data['body'],
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        $thread->incrementReplyCount();

        return $reply;
    }

    /**
     * Pin/unpin a thread (moderator action).
     */
    public function togglePin(int $threadId): ForumThread
    {
        $thread = ForumThread::findOrFail($threadId);
        $thread->is_pinned ? $thread->unpin() : $thread->pin();
        return $thread->fresh();
    }

    /**
     * Lock/unlock a thread (moderator action).
     */
    public function toggleLock(int $threadId): ForumThread
    {
        $thread = ForumThread::findOrFail($threadId);
        $thread->is_locked ? $thread->unlock() : $thread->lock();
        return $thread->fresh();
    }

    /**
     * Delete a thread (soft delete, moderator action).
     */
    public function deleteThread(int $threadId): void
    {
        $thread = ForumThread::findOrFail($threadId);
        $thread->delete();
    }

    /**
     * Delete a reply (soft delete, moderator/author).
     */
    public function deleteReply(int $replyId): void
    {
        $reply = ForumReply::findOrFail($replyId);
        $reply->thread->decrementReplyCount();
        $reply->delete();
    }

    /**
     * Mark a reply as accepted answer (thread author action).
     */
    public function acceptAnswer(int $replyId): ForumReply
    {
        $reply = ForumReply::findOrFail($replyId);
        $reply->markAsAccepted();
        return $reply->fresh();
    }

    /**
     * Create or ensure a forum exists for a course.
     */
    public function ensureCourseForum(int $courseId, string $courseTitle): Forum
    {
        return Forum::firstOrCreate(
            ['course_id' => $courseId],
            [
                'title' => "{$courseTitle} Discussion",
                'description' => "Discuss {$courseTitle} topics, ask questions, and share insights.",
            ]
        );
    }

    /**
     * Search threads across forums.
     */
    public function searchThreads(string $query, int $perPage = 20): LengthAwarePaginator
    {
        return ForumThread::where('title', 'LIKE', "%{$query}%")
            ->orWhere('body', 'LIKE', "%{$query}%")
            ->with(['user:id,first_name,last_name,avatar', 'forum:id,title'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
