<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrimeCategory extends Model
{
    use HasFactory;

    protected $table = 'crime_department_crime_categories';

    protected $fillable = [
        'category_name',
        'category_code',
        'description',
        'source_system',
        'severity_level',
        'color_code',
        'icon',
        'is_active',
    ];

    public function incidents()
    {
        return $this->hasMany(CrimeIncident::class, 'crime_category_id');
    }

    public function alerts()
    {
        return $this->hasMany(CrimeAlert::class);
    }
}
