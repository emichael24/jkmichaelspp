<?php

namespace App\Http\Controllers;

use App\Models\Forum;
use App\Services\CommunityAccessService;
use App\Services\ForumService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForumController extends Controller
{
    public function __construct(private ForumService $forumService) {}

    /**
     * List all accessible forums.
     */
    public function index(): Response
    {
        $forums = $this->forumService->getForumsForUser(auth()->id());

        return Inertia::render('Forums/Index', [
            'forums' => $forums,
        ]);
    }

    /**
     * Show a forum with its threads.
     */
    public function show(int $forumId, Request $request): Response
    {
        $forum = app(CommunityAccessService::class)->forum($forumId, $request->user())->load('course:id,title', 'cohort:id,name');
        $threads = $this->forumService->getThreads($forumId);

        return Inertia::render('Forums/Show', [
            'forum' => $forum,
            'threads' => $threads,
        ]);
    }

    /**
     * Show a thread with replies.
     */
    public function thread(int $threadId): Response
    {
        $thread = $this->forumService->getThread($threadId);

        return Inertia::render('Forums/Thread', [
            'thread' => $thread,
        ]);
    }

    /**
     * Create a new thread.
     */
    public function storeThread(int $forumId, Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $this->forumService->createThread($forumId, auth()->id(), $validated);

        return redirect()->route('forums.show', $forumId)->with('success', 'Thread created successfully.');
    }

    /**
     * Create a reply.
     */
    public function storeReply(int $threadId, Request $request)
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'parent_id' => 'nullable|exists:forum_replies,id',
        ]);

        $this->forumService->createReply($threadId, auth()->id(), $validated);

        return back()->with('success', 'Reply posted successfully.');
    }

    /**
     * Pin/unpin a thread.
     */
    public function togglePin(int $threadId)
    {
        $this->forumService->togglePin($threadId);

        return back()->with('success', 'Thread pin status updated.');
    }

    /**
     * Lock/unlock a thread.
     */
    public function toggleLock(int $threadId)
    {
        $this->forumService->toggleLock($threadId);

        return back()->with('success', 'Thread lock status updated.');
    }

    /**
     * Delete a thread.
     */
    public function destroyThread(int $threadId)
    {
        $this->forumService->deleteThread($threadId);

        return back()->with('success', 'Thread deleted.');
    }

    /**
     * Delete a reply.
     */
    public function destroyReply(int $replyId)
    {
        $this->forumService->deleteReply($replyId);

        return back()->with('success', 'Reply deleted.');
    }

    /**
     * Accept a reply as answer.
     */
    public function acceptAnswer(int $replyId)
    {
        $this->forumService->acceptAnswer($replyId);

        return back()->with('success', 'Answer accepted.');
    }

    /**
     * Search threads.
     */
    public function search(Request $request): Response
    {
        $query = $request->input('q', '');
        $results = $query ? $this->forumService->searchThreads($query) : collect();

        return Inertia::render('Forums/Index', [
            'forums' => $this->forumService->getForumsForUser(auth()->id()),
            'searchResults' => $results,
            'searchQuery' => $query,
        ]);
    }
}
