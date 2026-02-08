<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrimeIncident extends Model
{
    use HasFactory;

    protected $table = 'crime_department_crime_incidents';

    protected $fillable = [
        'incident_code',
        'crime_category_id',
        'barangay_id',
        'incident_title',
        'incident_description',
        'incident_date',
        'incident_time',
        'latitude',
        'longitude',
        'address_details',
        'victim_count',
        'suspect_count',
        'status',
        'clearance_status',
        'clearance_date',
        'modus_operandi',
        'weather_condition',
        'reported_by',
        'assigned_officer',
    ];

    protected $casts = [
        'incident_date' => 'datetime',
        'clearance_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(CrimeCategory::class, 'crime_category_id');
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
