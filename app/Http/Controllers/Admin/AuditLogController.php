<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __construct(private AuditService $auditService) {}

    /**
     * List audit logs with filters.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only('user_id', 'action', 'auditable_type', 'date_from', 'date_to');

        $logs = $this->auditService->getLogs($filters, 25);
        $stats = $this->auditService->getStats(30);

        // Get unique actions and entity types for filters
        $actions = \App\Models\AuditLog::distinct()->pluck('action')->sort()->values();
        $entityTypes = \App\Models\AuditLog::distinct()
            ->pluck('auditable_type')
            ->map(fn($type) => class_basename($type))
            ->sort()
            ->values();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'stats' => $stats,
            'actions' => $actions,
            'entityTypes' => $entityTypes,
            'filters' => $filters,
        ]);
    }

    /**
     * Show audit log detail.
     */
    public function show(int $id): Response
    {
        $log = \App\Models\AuditLog::with('user:id,first_name,last_name,email')->findOrFail($id);

        return Inertia::render('Admin/AuditLogs/Show', [
            'log' => $log,
            'changedFields' => $log->getChangedFields(),
        ]);
    }

    /**
     * Export audit logs (CSV).
     */
    public function export(Request $request)
    {
        $filters = $request->only('user_id', 'action', 'auditable_type', 'date_from', 'date_to');
        $logs = $this->auditService->getLogs($filters, 10000);

        $csv = "ID,User,Action,Entity Type,Entity ID,IP Address,Date\n";
        foreach ($logs as $log) {
            $userName = $log->user ? "{$log->user->first_name} {$log->user->last_name}" : 'System';
            $csv .= "{$log->id},{$userName},{$log->action}," .
                class_basename($log->auditable_type) . ",{$log->auditable_id}," .
                "{$log->ip_address},{$log->created_at}\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="audit-logs-' . now()->format('Y-m-d') . '.csv"');
    }
}
