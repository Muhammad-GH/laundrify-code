<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'id', 'customer_name', 'phone_number','email','password','profile_picture','status','otp'
    ];
}
