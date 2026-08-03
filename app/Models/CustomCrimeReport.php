<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomCrimeReport extends Model
{
    protected $table = 'crime_department_custom_reports';

    protected $fillable = [
        'title',
        'purpose',
        'incident_codes',
        'created_by',
    ];

    protected $casts = [
        'incident_codes' => 'array',
    ];
}
