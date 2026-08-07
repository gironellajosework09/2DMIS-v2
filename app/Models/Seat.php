<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    protected $table = 'tbl_seats';

    public $timestamps = false;

    protected $fillable = [
        'program',
        'name',
        'town',
        'section',
        'box',
        'row',
        'seat',
    ];
}
