<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlertRule extends Model
{
    use SoftDeletes;

    protected $table = 'crime_department_alert_rules';

    protected $fillable = [
        'rule_name',
        'rule_type',
        'severity',
        'rule_condition',
        'conditions_data',
        'enabled',
        'trigger_count',
        'last_triggered_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'conditions_data' => 'array',
        'enabled' => 'boolean',
        'last_triggered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user who created this rule
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this rule
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope: Get only enabled rules
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: Get rules by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('rule_type', $type);
    }

    /**
     * Scope: Get rules by severity
     */
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Check if this rule should trigger based on crime data
     */
    public function shouldTrigger($crimeData)
    {
        // TODO: Implement rule trigger logic based on rule_type and conditions
        return false;
    }

    /**
     * Record a trigger event for this rule
     */
    public function recordTrigger()
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);
    }
}
