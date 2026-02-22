<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertSettings extends Model
{
    protected $table = 'crime_department_alert_settings';

    protected $fillable = [
        'user_id',
        'setting_type',
        'general_settings',
        'crime_thresholds',
        'location_thresholds',
        'notification_settings',
    ];

    protected $casts = [
        'general_settings' => 'array',
        'crime_thresholds' => 'array',
        'location_thresholds' => 'array',
        'notification_settings' => 'array',
    ];

    /**
     * Get the user that owns the settings
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create global settings
     */
    public static function getGlobal()
    {
        return self::where('setting_type', 'global')
            ->where('user_id', null)
            ->first();
    }

    /**
     * Get or create user settings
     */
    public static function getForUser($userId)
    {
        return self::where('setting_type', 'user')
            ->where('user_id', $userId)
            ->first();
    }
}
