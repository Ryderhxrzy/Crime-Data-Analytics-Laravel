<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Events\CrimeIncidentUpdated;
use App\Events\CrimeIncidentDeleted;
use App\Services\CacheService;

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

    /**
     * Broadcast events when crime incidents are created, updated, or deleted
     * Enables real-time updates on the mapping page via WebSockets
     */
    protected static function booted(): void
    {
        static::created(function (CrimeIncident $incident) {
            // Broadcast new incident event
            broadcast(new CrimeIncidentUpdated(
                $incident->load(['category', 'barangay']),
                'created'
            ));
            // Invalidate filter cache so next page load gets fresh data
            CacheService::invalidateFilters();
        });

        static::updated(function (CrimeIncident $incident) {
            // Broadcast updated incident event
            broadcast(new CrimeIncidentUpdated(
                $incident->load(['category', 'barangay']),
                'updated'
            ));
            // Invalidate filter cache
            CacheService::invalidateFilters();
        });

        static::deleted(function (CrimeIncident $incident) {
            // Broadcast deleted incident event
            broadcast(new CrimeIncidentDeleted($incident->id));
            // Invalidate filter cache
            CacheService::invalidateFilters();
        });
    }
}
