<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    use HasFactory;

    protected $table = 'crime_department_barangays';

    protected $fillable = [
        'barangay_name',
        'barangay_code',
        'city_municipality',
        'province',
        'region',
        'latitude',
        'longitude',
        'population',
        'area_sqkm',
        'is_active',
    ];

    public function incidents()
    {
        return $this->hasMany(CrimeIncident::class);
    }

    public function alerts()
    {
        return $this->hasMany(CrimeAlert::class);
    }
}
