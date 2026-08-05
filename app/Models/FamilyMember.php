<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMember extends Model
{
    protected $table = 'tbl_family_members';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'relative_id',
        'relationship',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function relative(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'relative_id');
    }
}
