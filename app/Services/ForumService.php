<?php

namespace App\Services;

use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ForumService
{
    /**
     * Get forums for a user (general + enrolled course forums + cohort forums).
     */
    public function getForumsForUser(int $userId): array
    {
        $accessible = app(CommunityAccessService::class)->forums(User::findOrFail($userId));
        $general = (clone $accessible)->where('is_general', true)->withCount('threads')->orderBy('sort_order')->get();
        $courseForums = (clone $accessible)->whereNotNull('course_id')->whereNull('cohort_id')->with('course:id,title')->withCount('threads')->orderBy('sort_order')->get();
        $cohortForums = (clone $accessible)->whereNotNull('cohort_id')->with('cohort:id,name')->withCount('threads')->orderBy('sort_order')->get();

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
        app(CommunityAccessService::class)->forum($forumId, auth()->user());

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
        app(CommunityAccessService::class)->forum($forumId, User::findOrFail($userId));

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
        $thread = ForumThread::findOrFail($threadId);
        app(CommunityAccessService::class)->forum($thread->forum_id, auth()->user());

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

        app(CommunityAccessService::class)->forum($thread->forum_id, User::findOrFail($userId));
        abort_if($thread->is_locked, 409, 'This thread is locked.');
        if (! empty($data['parent_id'])) {
            abort_unless(ForumReply::where('thread_id', $threadId)->whereKey($data['parent_id'])->exists(), 422, 'Parent reply must belong to this thread.');
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
        $forum = app(CommunityAccessService::class)->forum($thread->forum_id, auth()->user());
        abort_unless(app(CommunityAccessService::class)->canModerate($forum, auth()->user()), 403);
        $thread->is_pinned ? $thread->unpin() : $thread->pin();

        return $thread->fresh();
    }

    /**
     * Lock/unlock a thread (moderator action).
     */
    public function toggleLock(int $threadId): ForumThread
    {
        $thread = ForumThread::findOrFail($threadId);
        $forum = app(CommunityAccessService::class)->forum($thread->forum_id, auth()->user());
        abort_unless(app(CommunityAccessService::class)->canModerate($forum, auth()->user()), 403);
        $thread->is_locked ? $thread->unlock() : $thread->lock();

        return $thread->fresh();
    }

    /**
     * Delete a thread (soft delete, moderator action).
     */
    public function deleteThread(int $threadId): void
    {
        $thread = ForumThread::findOrFail($threadId);
        $forum = app(CommunityAccessService::class)->forum($thread->forum_id, auth()->user());
        abort_unless(app(CommunityAccessService::class)->canModerate($forum, auth()->user()) || $thread->user_id === auth()->id(), 403);
        $thread->delete();
    }

    /**
     * Delete a reply (soft delete, moderator/author).
     */
    public function deleteReply(int $replyId): void
    {
        $reply = ForumReply::findOrFail($replyId);
        $forum = app(CommunityAccessService::class)->forum($reply->thread->forum_id, auth()->user());
        abort_unless($reply->user_id === auth()->id() || app(CommunityAccessService::class)->canModerate($forum, auth()->user()), 403);
        $reply->thread->decrementReplyCount();
        $reply->delete();
    }

    /**
     * Mark a reply as accepted answer (thread author action).
     */
    public function acceptAnswer(int $replyId): ForumReply
    {
        $reply = ForumReply::findOrFail($replyId);
        $forum = app(CommunityAccessService::class)->forum($reply->thread->forum_id, auth()->user());
        abort_unless($reply->thread->user_id === auth()->id() || app(CommunityAccessService::class)->canModerate($forum, auth()->user()), 403);
        $reply->markAsAccepted();

        return $reply->fresh();
    }

    /**
     * Create or ensure a forum exists for a course.
     */
    public function ensureCourseForum(int $courseId, string $courseTitle, ?int $organizationId = null): Forum
    {
        return Forum::firstOrCreate(
            ['course_id' => $courseId, 'organization_id' => $organizationId],
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
        return ForumThread::whereIn('forum_id', app(CommunityAccessService::class)->forums(auth()->user())->select('forums.id'))
            ->where(fn ($text) => $text->where('title', 'LIKE', "%{$query}%")->orWhere('body', 'LIKE', "%{$query}%"))
            ->with(['user:id,first_name,last_name,avatar', 'forum:id,title'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
