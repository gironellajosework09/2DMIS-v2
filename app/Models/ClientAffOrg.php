<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAffOrg extends Model
{
    protected $table = 'tbl_client_aff_orgs';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'organization',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
