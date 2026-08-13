<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    protected $table = 'tbl_results';

    public $timestamps = false;

    protected $guarded = [];
}
