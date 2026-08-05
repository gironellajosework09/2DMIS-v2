<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    protected $table = 'tbl_municipalities';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'code',
    ];

    public function barangays(): HasMany
    {
        return $this->hasMany(Barangay::class, 'municipality_id');
    }
}
