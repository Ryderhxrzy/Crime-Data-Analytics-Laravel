<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A staff account of the Crime Data Analytics system.
 *
 * Lives in this app's own table (see the 2026_09_05 migration) because the
 * shared centralized_admin_user table only knows admin roles. Signs in through
 * the same login form as admins, under the 'staff' auth guard.
 */
class StaffUser extends Authenticatable
{
    use Notifiable;

    public const ROLE = 'staff';

    protected $table = 'crime_department_staff';

    protected $fillable = [
        'full_name',
        'email',
        'password_hash',
        'position',
        'contact_number',
        'is_active',
        'must_change_password',
        'credentials_sent_at',
        'attempt_count',
        'locked_until',
        'ip_address',
        'last_login',
        'last_activity',
        'password_changed_at',
        'created_by',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'must_change_password' => 'boolean',
        'attempt_count'        => 'integer',
        'credentials_sent_at'  => 'datetime',
        'locked_until'         => 'datetime',
        'last_login'           => 'datetime',
        'last_activity'        => 'datetime',
        'password_changed_at'  => 'datetime',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** The session payload the rest of the app reads (sidebar, audit, profile) */
    public function sessionPayload(): array
    {
        return [
            'id'                   => $this->id,
            'email'                => $this->email,
            'full_name'            => $this->full_name,
            'role'                 => self::ROLE,
            'account_type'         => self::ROLE,
            'department'           => 'crime_data_department',
            'department_name'      => 'Crime Data Department',
            'position'             => $this->position,
            'must_change_password' => (bool) $this->must_change_password,
        ];
    }
}
