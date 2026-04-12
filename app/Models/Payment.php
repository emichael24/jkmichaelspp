<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'gateway',
        'gateway_reference',
        'gateway_response',
        'amount',
        'currency',
        'status',
        'paid_at',
        'refunded_at',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    // ── Relationships ──

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ──

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    // ── Helpers ──

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function markSuccessful(array $gatewayResponse = []): void
    {
        $this->update([
            'status' => 'success',
            'gateway_response' => $gatewayResponse,
            'paid_at' => now(),
        ]);
    }

    public function markFailed(array $gatewayResponse = []): void
    {
        $this->update([
            'status' => 'failed',
            'gateway_response' => $gatewayResponse,
        ]);
    }

    public function markRefunded(): void
    {
        $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }
}
