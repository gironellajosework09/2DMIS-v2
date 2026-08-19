<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMunicipality extends Model
{
    use HasFactory;

    protected $table = 'tbl_user_municipalities';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'municipality_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
