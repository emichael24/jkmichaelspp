<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /**
     * Log a model creation.
     */
    public function logCreate(Model $model, ?int $userId = null): AuditLog
    {
        return AuditLog::log(
            'create',
            get_class($model),
            $model->id,
            null,
            $model->toArray(),
            $userId
        );
    }

    /**
     * Log a model update.
     */
    public function logUpdate(Model $model, array $oldValues, ?int $userId = null): AuditLog
    {
        return AuditLog::log(
            'update',
            get_class($model),
            $model->id,
            $oldValues,
            $model->toArray(),
            $userId
        );
    }

    /**
     * Log a model deletion.
     */
    public function logDelete(Model $model, ?int $userId = null): AuditLog
    {
        return AuditLog::log(
            'delete',
            get_class($model),
            $model->id,
            $model->toArray(),
            null,
            $userId
        );
    }

    /**
     * Log a user login.
     */
    public function logLogin(int $userId): AuditLog
    {
        return AuditLog::log(
            'login',
            'App\\Models\\User',
            $userId,
            null,
            ['logged_in_at' => now()->toIso8601String()],
            $userId
        );
    }

    /**
     * Log a user logout.
     */
    public function logLogout(int $userId): AuditLog
    {
        return AuditLog::log(
            'logout',
            'App\\Models\\User',
            $userId,
            null,
            ['logged_out_at' => now()->toIso8601String()],
            $userId
        );
    }

    /**
     * Log a custom action.
     */
    public function logAction(
        string $action,
        Model $model,
        ?array $details = null,
        ?int $userId = null
    ): AuditLog {
        return AuditLog::log(
            $action,
            get_class($model),
            $model->id,
            null,
            $details,
            $userId
        );
    }

    /**
     * Get audit trail for a specific model.
     */
    public function getAuditTrail(string $modelClass, int $modelId, int $limit = 50): array
    {
        return AuditLog::forModel($modelClass, $modelId)
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get recent activity for a user.
     */
    public function getUserActivity(int $userId, int $limit = 50): array
    {
        return AuditLog::byUser($userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get system-wide audit logs with filters.
     */
    public function getLogs(array $filters = [], int $perPage = 25)
    {
        $query = AuditLog::with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at');

        if (! empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (! empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        if (! empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['days'])) {
            $query->recent($filters['days']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get summary statistics.
     */
    public function getStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'total_actions' => AuditLog::where('created_at', '>=', $since)->count(),
            'unique_users' => AuditLog::where('created_at', '>=', $since)->distinct('user_id')->count('user_id'),
            'actions_breakdown' => AuditLog::where('created_at', '>=', $since)
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->pluck('count', 'action')
                ->toArray(),
            'top_entities' => AuditLog::where('created_at', '>=', $since)
                ->selectRaw('auditable_type, COUNT(*) as count')
                ->groupBy('auditable_type')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'auditable_type')
                ->toArray(),
        ];
    }
}
