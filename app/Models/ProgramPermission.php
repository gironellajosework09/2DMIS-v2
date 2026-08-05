<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramPermission extends Model
{
    use HasFactory;

    protected $table = 'tbl_program_permissions';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'program_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
