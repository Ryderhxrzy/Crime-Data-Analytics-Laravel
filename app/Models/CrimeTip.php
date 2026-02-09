<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrimeTip extends Model
{
    use HasFactory;

    protected $table = 'crime_deportment_report_tip';

    protected $fillable = [
        'crime_type',
        'location',
        'date_of_crime',
        'details',
        'status',
    ];

    protected $casts = [
        'date_of_crime' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
