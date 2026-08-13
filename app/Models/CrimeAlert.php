<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrimeAlert extends Model
{
    use HasFactory;

    protected $table = 'crime_department_crime_alerts';

    protected $fillable = [
        'alert_rule_id',
        'alert_code',
        'alert_title',
        'alert_type',
        'severity',
        'barangay_id',
        'street_name',
        'crime_category_id',
        'center_latitude',
        'center_longitude',
        'radius_meters',
        'alert_description',
        'incident_count',
        'related_incidents',
        'alert_status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    /**
     * $dates was removed in Laravel 10, so these came back as plain strings and
     * anything calling ->setTimezone() on resolved_at blew up with a 500 — which
     * is why the Alert History page rendered nothing. They are real casts now.
     */
    protected $casts = [
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'incident_count' => 'integer',
        'center_latitude' => 'float',
        'center_longitude' => 'float',
    ];

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function category()
    {
        return $this->belongsTo(CrimeCategory::class, 'crime_category_id');
    }

    public function rule()
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeActiveStatus($query)
    {
        return $query->whereIn('alert_status', ['active', 'acknowledged', 'investigating']);
    }
}
