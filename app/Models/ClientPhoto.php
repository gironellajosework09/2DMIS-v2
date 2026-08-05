<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPhoto extends Model
{
    protected $table = 'tbl_client_photos';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'photo_path',
        'captured_from',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
