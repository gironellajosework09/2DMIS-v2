<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Household extends Model
{
    protected $table = 'tbl_household';

    public $timestamps = false;

    protected $fillable = [
        'household_id',
        'head_household',
    ];

    public function headClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'head_household');
    }
}
