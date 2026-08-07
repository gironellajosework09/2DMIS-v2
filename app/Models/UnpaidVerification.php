<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnpaidVerification extends Model
{
    protected $table = 'tbl_unpaid_verifications';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'municipality_id',
        'is_proxy',
        'proxy_lastname',
        'proxy_firstname',
        'proxy_middlename',
        'proxy_relationship',
        'proxy_phone',
        'proxy_birthdate',
        'proxy_gender',
        'proxy_occupation',
        'proxy_monthlyincome',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'client_id' => 'integer',
            'municipality_id' => 'integer',
            'is_proxy' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_id');
    }
}
