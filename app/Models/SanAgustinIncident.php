<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Incident record scoped to Barangay San Agustin, Quezon City.
 *
 * barangay_name and category_name are plain strings here — this table has no
 * foreign keys to the barangay or category tables.
 */
class SanAgustinIncident extends Model
{
    use HasFactory;

    protected $table = 'crime_department_san_agustin_incidents';

    protected $fillable = [
        'incident_code',
        'record_type',
        'category_name',
        'barangay_name',
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
        'incident_date'  => 'date',
        'clearance_date' => 'date',
        'latitude'       => 'float',
        'longitude'      => 'float',
        'victim_count'   => 'integer',
        'suspect_count'  => 'integer',
    ];

    public function scopeCrimes($query)
    {
        return $query->where('record_type', 'crime');
    }

    public function scopeIncidents($query)
    {
        return $query->where('record_type', 'incident');
    }
}
