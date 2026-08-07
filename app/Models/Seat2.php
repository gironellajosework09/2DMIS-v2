<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seat2 extends Model
{
    protected $table = 'tbl_seats2';

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
