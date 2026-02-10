<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileUser extends Model
{
    use HasFactory;

    protected $table = 'mobile_user';

    protected $fillable = [
        'full_name',
        'email',
        'password_hash',
    ];

    protected $hidden = [
        'password_hash',
    ];
}
