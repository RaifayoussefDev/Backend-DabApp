<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInfo extends Model
{
    protected $guarded = [];

    protected $casts = [
        'address_visible' => 'boolean',
        'phone_visible'    => 'boolean',
        'email_visible'    => 'boolean',
        'hours_visible'    => 'boolean',
    ];
}
