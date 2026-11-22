<?php

namespace App\Models;

use App\Models\Traits\RecordsDomainAudit;
use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    use RecordsDomainAudit;

    protected $guarded = ['id'];

    protected $hidden = ['reason'];

    protected $casts = ['reason' => 'encrypted', 'receipt' => 'array', 'amount_minor' => 'integer', 'submitted_at' => 'datetime', 'reconciled_at' => 'datetime', 'processed_at' => 'datetime'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
