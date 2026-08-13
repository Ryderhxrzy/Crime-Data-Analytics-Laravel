<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A user's preferences for this system, keyed by email because accounts are
 * owned by the centralized portal rather than by this database.
 */
class UserPreference extends Model
{
    protected $table = 'crime_department_user_preferences';

    protected $fillable = [
        'user_email',
        'display_name',
        'contact_number',
        'position',
        'default_view_mode',
        'default_time_period',
        'default_barangay',
        'rows_per_page',
        'suggestion_language',
        'alert_sound',
        'alert_min_severity',
    ];

    protected $casts = [
        'rows_per_page' => 'integer',
        'alert_sound' => 'boolean',
    ];

    /** What a user gets before they have ever saved anything */
    public const DEFAULTS = [
        'display_name' => null,
        'contact_number' => null,
        'position' => null,
        'default_view_mode' => 'markers',
        'default_time_period' => 'all',
        'default_barangay' => 'San Agustin',
        'rows_per_page' => 25,
        'suggestion_language' => 'en',
        'alert_sound' => false,
        'alert_min_severity' => 'low',
    ];

    /** Email of the signed-in user: JWT session first, local auth second */
    public static function currentEmail(): ?string
    {
        return session('auth_user.email') ?? auth()->user()?->email;
    }

    /**
     * The current user's preferences as a plain array, always complete.
     * Never throws: a missing table or no session just yields the defaults.
     */
    public static function current(): array
    {
        $email = self::currentEmail();

        if (! $email) {
            return self::DEFAULTS;
        }

        try {
            $row = self::where('user_email', $email)->first();
        } catch (\Throwable $e) {
            return self::DEFAULTS;
        }

        if (! $row) {
            return self::DEFAULTS;
        }

        return array_merge(
            self::DEFAULTS,
            array_filter(
                $row->only(array_keys(self::DEFAULTS)),
                fn ($value) => $value !== null && $value !== ''
            )
        );
    }
}
