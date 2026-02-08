<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrimeAlert extends Model
{
    use HasFactory;

    protected $table = 'crime_department_crime_alerts';

    protected $fillable = [
        'alert_code',
        'alert_title',
        'alert_type',
        'severity',
        'barangay_id',
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

    protected $dates = [
        'acknowledged_at',
        'resolved_at',
        'created_at',
        'updated_at',
    ];

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function category()
    {
        return $this->belongsTo(CrimeCategory::class, 'crime_category_id');
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
