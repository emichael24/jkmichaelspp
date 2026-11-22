<?php

namespace App\Http\Controllers\AI;

use App\Exceptions\ExternalIntegrationException;
use App\Http\Controllers\Controller;
use App\Models\AiJob;
use App\Models\Cohort;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminAIController extends Controller
{
    public function __construct(protected AIService $ai) {}

    // ─── AI Insights Dashboard ────────────────────────────────────────────────────
    public function index()
    {
        return Inertia::render('Admin/AI/Insights', [
            'recentInsights' => AiJob::where('job_type', 'insights')
                ->latest()
                ->limit(5)
                ->get(['id', 'uuid', 'output', 'created_at', 'status']),
        ]);
    }

    // ─── Generate platform-level insights ────────────────────────────────────────
    public function generatePlatformInsights(Request $request)
    {
        $request->validate(['type' => 'nullable|in:overview,engagement,learning_outcomes,revenue,retention']);
        $data = $this->collectPlatformStats();

        $job = AiJob::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'job_type' => 'insights',
            'status' => 'processing',
            'input_payload' => ['context' => 'platform', 'type' => $request->input('type', 'overview'), 'snapshot_date' => now()->toDateString()],
        ]);

        try {
            $insights = $this->ai->generateInsights($data, 'platform '.$request->input('type', 'overview'));

            $job->update([
                'status' => 'completed',
                'output' => $insights,
                'completed_at' => now(),
            ]);

            return response()->json(['insights' => $insights, 'data' => $data]);
        } catch (ExternalIntegrationException $e) {
            $job->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error_message' => 'Insight generation could not finish. Please retry or contact support.']);
            throw $e;
        }
    }

    // ─── Generate cohort-specific insights ────────────────────────────────────────
    public function generateCohortInsights(Request $request)
    {
        $request->validate([
            'cohort_id' => 'required|exists:cohorts,id',
        ]);

        $cohort = Cohort::with(['members.user'])->findOrFail($request->input('cohort_id'));

        $data = [
            'cohort_name' => $cohort->name,
            'total_members' => $cohort->members->count(),
            'active_members' => $cohort->members->where('status', 'active')->count(),
            'avg_progress' => DB::table('course_enrollments')->where('course_id', $cohort->course_id)
                ->whereIn('user_id', $cohort->members->where('role', 'learner')->pluck('user_id'))->avg('progress_pct'),
            'course' => $cohort->course->title ?? 'N/A',
        ];

        $insights = $this->ai->generateInsights($data, 'cohort');

        return response()->json(['insights' => $insights]);
    }

    // ─── AI Content generation for admin (course descriptions, announcements) ────
    public function generateContent(Request $request)
    {
        $request->validate([
            'type' => 'required|in:course_description,announcement,email_subject,welcome_message',
            'context' => 'required|string|min:20|max:2000',
            'tone' => 'nullable|in:professional,friendly,motivational,formal',
        ]);

        $systemPrompts = [
            'course_description' => 'You are a professional copywriter for JK Michaels LMS. Write engaging, SEO-friendly course descriptions.',
            'announcement' => 'You are a platform communications manager. Write clear, engaging platform announcements.',
            'email_subject' => 'Generate 5 compelling email subject line options. Return as a JSON array of strings.',
            'welcome_message' => 'Write a warm, professional welcome message for new platform users.',
        ];

        $content = $this->ai->chat(
            $request->input('context'),
            $systemPrompts[$request->input('type')].' Use a '.$request->input('tone', 'professional').' tone.',
            0.75,
            1000
        );

        return response()->json(['content' => $content]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    protected function collectPlatformStats(): array
    {
        return [
            'total_users' => DB::table('users')->count(),
            'active_users_30d' => DB::table('users')->where('last_login_at', '>=', now()->subDays(30))->count(),
            'total_courses' => DB::table('courses')->where('is_published', true)->count(),
            'total_enrollments' => DB::table('course_enrollments')->count(),
            'avg_completion_rate' => DB::table('course_enrollments')->avg('progress_pct') ?? 0,
            'total_revenue_ngn' => DB::table('paystack_transactions')->where('status', 'success')->sum('amount'),
            'pending_approvals' => DB::table('courses')->where('approval_status', 'pending_review')->count(),
            'active_cohorts' => DB::table('cohorts')->where('status', 'active')->count(),
            'internship_active' => DB::table('internship_enrollments')->where('status', 'active')->count(),
        ];
    }
}
