<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CannedResponse extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'canned_responses';

    protected $fillable = [
        'title',
        'body',
    ];
}
