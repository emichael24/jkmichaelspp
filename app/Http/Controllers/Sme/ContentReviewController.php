<?php

namespace App\Http\Controllers\Sme;

use App\Http\Controllers\Controller;
use App\Models\ContentApproval;
use App\Models\ContentVersion;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Services\ContentWorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContentReviewController extends Controller
{
    public function __construct(
        private ContentWorkflowService $workflowService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Content Library — All content with workflow status
    |--------------------------------------------------------------------------
    */

    public function library(Request $request)
    {
        $type = $request->get('type', 'all'); // lesson, question, course, all

        $query = ContentApproval::with(['approvable', 'submitter', 'reviewer'])
            ->when($type !== 'all', fn ($q) => $q->where('approvable_type', $this->resolveType($type)))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('submitted_at');

        $approvals = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => ContentApproval::pending()->count(),
            'approved' => ContentApproval::approved()->count(),
            'rejected' => ContentApproval::rejected()->count(),
            'changes_requested' => ContentApproval::where('status', 'changes_requested')->count(),
        ];

        return Inertia::render('Sme/ContentLibrary', [
            'approvals' => $approvals,
            'stats' => $stats,
            'filters' => $request->only(['type', 'status']),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Pending Review Queue
    |--------------------------------------------------------------------------
    */

    public function pendingQueue(Request $request)
    {
        $approvals = $this->workflowService->getPendingApprovals(
            $request->type ? $this->resolveType($request->type) : null
        );

        return Inertia::render('Sme/PendingQueue', [
            'approvals' => $approvals,
            'filters' => $request->only(['type']),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Review Detail (with diff view)
    |--------------------------------------------------------------------------
    */

    public function review(Request $request, ContentApproval $approval)
    {
        $approval->load(['approvable', 'submitter', 'reviewer']);

        // Get previous version for diff
        $previousVersion = ContentVersion::forContent(
            $approval->approvable_type,
            $approval->approvable_id
        )->latestFirst()->first();

        // Approval history
        $history = $this->workflowService->getApprovalHistory($approval->approvable);

        return Inertia::render('Sme/ContentReview', [
            'approval' => $approval,
            'content' => $approval->approvable,
            'previousVersion' => $previousVersion,
            'history' => $history,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Approve Content
    |--------------------------------------------------------------------------
    */

    public function approve(Request $request, ContentApproval $approval)
    {
        $request->validate([
            'notes' => 'nullable|string|max:2000',
            'publish' => 'boolean',
        ]);

        $this->workflowService->approve(
            $approval,
            $request->user(),
            $request->notes,
            $request->boolean('publish', false)
        );

        return back()->with('success', 'Content approved successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Reject Content
    |--------------------------------------------------------------------------
    */

    public function reject(Request $request, ContentApproval $approval)
    {
        $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        $this->workflowService->reject($approval, $request->user(), $request->notes);

        return back()->with('success', 'Content rejected.');
    }

    /*
    |--------------------------------------------------------------------------
    | Request Changes
    |--------------------------------------------------------------------------
    */

    public function requestChanges(Request $request, ContentApproval $approval)
    {
        $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        $this->workflowService->requestChanges($approval, $request->user(), $request->notes);

        return back()->with('success', 'Changes requested.');
    }

    /*
    |--------------------------------------------------------------------------
    | Submit Content for Review
    |--------------------------------------------------------------------------
    */

    public function submit(Request $request, string $type, int $id)
    {
        $content = $this->resolveContent($type, $id);

        $this->workflowService->submitForReview($content, $request->user());

        return back()->with('success', 'Content submitted for review.');
    }

    /*
    |--------------------------------------------------------------------------
    | Version History
    |--------------------------------------------------------------------------
    */

    public function versions(string $type, int $id)
    {
        $content = $this->resolveContent($type, $id);
        $versions = $this->workflowService->getVersionHistory($content);

        return Inertia::render('Sme/Versions', [
            'content' => $content,
            'versions' => $versions,
            'contentType' => $type,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Revert to Version
    |--------------------------------------------------------------------------
    */

    public function revert(Request $request, string $type, int $id, int $versionId)
    {
        $content = $this->resolveContent($type, $id);

        $this->workflowService->revertToVersion($content, $versionId, $request->user());

        return back()->with('success', 'Content reverted to previous version.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function resolveType(string $type): string
    {
        return match ($type) {
            'lesson' => Lesson::class,
            'question' => Question::class,
            'course' => Course::class,
            default => $type,
        };
    }

    private function resolveContent(string $type, int $id)
    {
        return match ($type) {
            'lesson' => Lesson::findOrFail($id),
            'question' => Question::findOrFail($id),
            'course' => Course::findOrFail($id),
            default => throw new \InvalidArgumentException("Unknown content type: {$type}"),
        };
    }
}
