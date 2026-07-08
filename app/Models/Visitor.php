<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $fillable = [
        'visitor_token', 'ip_address', 'country_code', 'country_name',
        'page', 'referrer', 'user_agent', 'hits',
    ];
}
