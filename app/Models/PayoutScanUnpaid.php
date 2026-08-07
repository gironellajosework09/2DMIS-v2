<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutScanUnpaid extends Model
{
    protected $table = 'tbl_payout_scans_unpaid';

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'scanned_text',
        'scanned_by',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_id' => 'integer',
            'scanned_by' => 'integer',
            'scanned_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
