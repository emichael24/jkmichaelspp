<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CourseEnrollment;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReportController extends Controller
{
    private function report(Request $request): array
    {
        $org = $request->user()->organization;
        abort_unless($org?->is_active, 403);
        $filters = $request->validate(['status' => ['nullable', Rule::in(['active', 'completed', 'suspended', 'expired'])],
            'department' => ['nullable', Rule::exists('departments', 'id')->where('organization_id', $org->id)], 'overdue' => 'nullable|boolean']);
        $query = CourseEnrollment::whereHas('user', fn ($q) => $q->where('organization_id', $org->id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['department'] ?? null, fn ($q, $id) => $q->whereHas('user.departments', fn ($d) => $d->where('departments.id', $id)))
            ->when($filters['overdue'] ?? false, fn ($q) => $q->where('status', 'active')->where('deadline', '<', today()));

        return [$org, $filters, $query];
    }

    public function index(Request $request)
    {
        [$org, $filters, $query] = $this->report($request);

        return Inertia::render('Organization/Reports', ['organization' => $org->only('name'), 'filters' => $filters,
            'departments' => $org->departments()->orderBy('name')->get(['id', 'name']),
            'stats' => ['enrollments' => (clone $query)->count(), 'completed' => (clone $query)->where('status', 'completed')->count(),
                'overdue' => (clone $query)->where('status', 'active')->where('deadline', '<', today())->count()],
            'enrollments' => $query->with(['user:id,first_name,last_name,email', 'course:id,title'])->orderByDesc('id')->paginate(30)->withQueryString()]);
    }

    public function export(Request $request)
    {
        [$org, $filters, $query] = $this->report($request);
        AuditLog::log('learning_export', Organization::class, $org->id, null, ['filters' => $filters, 'rows' => (clone $query)->count()]);

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Learner', 'Email', 'Course', 'Status', 'Progress percent', 'Deadline', 'Completed at'], ',', '"', '');
            foreach ($query->with(['user:id,first_name,last_name,email', 'course:id,title'])->lazyById(500) as $enrollment) {
                $values = [$enrollment->user->full_name, $enrollment->user->email, $enrollment->course?->title ?? 'Archived course', $enrollment->status,
                    $enrollment->progress_pct, $enrollment->deadline?->format('Y-m-d') ?? '', $enrollment->completed_at?->toIso8601String() ?? ''];
                $values = array_map(fn ($value) => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".$value : $value, $values);
                fputcsv($stream, $values, ',', '"', '');
            }
            fclose($stream);
        }, 'organization-learning-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
