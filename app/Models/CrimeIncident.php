<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use App\Events\CrimeIncidentUpdated;
use App\Events\CrimeIncidentDeleted;

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

    public function personsInvolved()
    {
        return $this->hasMany(PersonsInvolved::class, 'incident_id');
    }

    public function evidence()
    {
        return $this->hasMany(Evidence::class, 'incident_id');
    }

    /**
     * Broadcast events when crime incidents are created, updated, or deleted
     * Enables real-time updates on the mapping page via WebSockets
     */
    protected static function booted(): void
    {
        static::created(function (CrimeIncident $incident) {
            Log::info('🚨 CrimeIncident.created model event triggered', [
                'incident_id' => $incident->id,
                'incident_title' => $incident->incident_title,
                'latitude' => $incident->latitude,
                'longitude' => $incident->longitude,
                'category_id' => $incident->crime_category_id,
                'barangay_id' => $incident->barangay_id
            ]);
            
            // Broadcast new incident event
            Log::info('📡 Broadcasting CrimeIncidentUpdated event...');
            broadcast(new CrimeIncidentUpdated(
                $incident->load(['category', 'barangay']),
                'created'
            ));
            
            Log::info('✅ CrimeIncidentUpdated event broadcasted successfully');
        });

        static::updated(function (CrimeIncident $incident) {
            Log::info('🔄 CrimeIncident.updated model event triggered', [
                'incident_id' => $incident->id,
                'incident_title' => $incident->incident_title
            ]);
            
            // Broadcast updated incident event
            broadcast(new CrimeIncidentUpdated(
                $incident->load(['category', 'barangay']),
                'updated'
            ));
        });

        static::deleted(function (CrimeIncident $incident) {
            Log::info('🗑️ CrimeIncident.deleted model event triggered', [
                'incident_id' => $incident->id
            ]);
            
            // Broadcast deleted incident event
            broadcast(new CrimeIncidentDeleted($incident->id));
        });
    }
}
