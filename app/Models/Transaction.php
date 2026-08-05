<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $table = 'tbl_transactions';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'program',
        'patient_name',
        'date_applied',
        'type',
        'remarks',
        'comments',
        'suggested_amount',
        'status',
        'amount_paid',
        'payout_date',
        'date_paid',
        'gwa',
        'units',
    ];

    protected function casts(): array
    {
        return [
            'client_id' => 'integer',
            'suggested_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
