<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CustomCrimeReport;
use App\Models\SanAgustinAiReport;
use App\Models\StaffUser;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Profile and Settings.
 *
 * Accounts themselves belong to the centralized portal (login.alertaraqc.com),
 * so this page does not pretend to change an email or a password. What it does
 * own is the person's working profile inside this system and the preferences
 * the crime pages actually read.
 */
class ProfileController extends Controller
{
    private function currentEmail(): ?string
    {
        return session('auth_user.email') ?? auth()->user()?->email;
    }

    private function currentUserId(): ?int
    {
        return session('auth_user.id') ?? auth()->id();
    }

    /** Identity as this system knows it: centralized JWT, local admin or staff */
    private function identity(): array
    {
        $account = currentAccount();
        $isStaff = ($account['account_type'] ?? null) === 'staff';

        if (session('jwt_token')) {
            $source = 'Centralized login (JWT)';
        } elseif ($isStaff) {
            $source = 'Staff account';
        } elseif ($account) {
            $source = 'Admin account';
        } else {
            $source = 'Guest';
        }

        return [
            'id' => $account['id'] ?? null,
            'email' => $account['email'] ?? 'Not signed in',
            'full_name' => $account['full_name'] ?? null,
            'role' => $account['role'] ?? 'user',
            'account_type' => $account['account_type'] ?? 'admin',
            'is_staff' => $isStaff,
            'must_change_password' => (bool) ($account['must_change_password'] ?? false),
            'department' => $account['department'] ?? null,
            'department_name' => $account['department_name'] ?? null,
            'position' => $account['position'] ?? null,
            'source' => $source,
        ];
    }

    /** The staff row behind the current session, or null for admins */
    private function currentStaff(): ?StaffUser
    {
        if (!isStaffAccount()) {
            return null;
        }

        try {
            return auth('staff')->user() ?? StaffUser::find(currentAccount()['id'] ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function show()
    {
        $identity = $this->identity();
        $email = $this->currentEmail();
        $preferences = UserPreference::current();

        // The account row, when this database can see it
        $account = null;
        if ($identity['is_staff']) {
            $account = $this->currentStaff();
        } elseif ($email) {
            try {
                $account = User::where('email', $email)->first();
            } catch (\Throwable $e) {
                $account = null;
            }
        }

        return view('profile', [
            'identity' => $identity,
            'account' => $account,
            'preferences' => $preferences,
            'activity' => $this->activityFor($email, $this->currentUserId()),
            'recentActions' => $this->recentActions($this->currentUserId()),
        ]);
    }

    /**
     * Staff change their own password here. Admin passwords belong to the
     * centralized portal and are not touched.
     */
    public function updatePassword(Request $request)
    {
        $staff = $this->currentStaff();

        if (!$staff) {
            return redirect()->route('profile')
                ->with('error', 'Only staff accounts change their password here. Admin passwords are managed by the centralized login.');
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|different:current_password',
        ], [
            'password.different' => 'The new password must be different from the current one.',
        ]);

        if (!password_verify($validated['current_password'], $staff->password_hash)) {
            return redirect()->route('profile')
                ->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $staff->update([
            'password_hash' => password_hash($validated['password'], PASSWORD_BCRYPT),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $session = session('auth_user', []);
        $session['must_change_password'] = false;
        session(['auth_user' => $session]);

        try {
            \App\Services\AuditLogService::log('CHANGE_PASSWORD', 'crime_department_staff', (int) $staff->id, ['email' => $staff->email]);
        } catch (\Throwable $e) {
            // The password change stands even when the audit table is missing
        }

        return redirect()->route('profile')->with('success', 'Your password has been updated.');
    }

    /**
     * What this person has actually done in the system. Every number is a real
     * count from this database, not a placeholder.
     */
    private function activityFor(?string $email, ?int $userId): array
    {
        $counts = [
            'audit_entries' => 0,
            'crime_reports' => 0,
            'ai_reports' => 0,
            'last_action_at' => null,
        ];

        if (! $email && ! $userId) {
            return $counts;
        }

        try {
            if ($userId && Schema::hasTable('crime_department_audit_logs')) {
                $counts['audit_entries'] = AuditLog::where('admin_id', $userId)->count();
                $counts['last_action_at'] = AuditLog::where('admin_id', $userId)
                    ->orderByDesc('created_at')->value('created_at');
            }
        } catch (\Throwable $e) {
            // A missing audit table must not take the profile page down
        }

        try {
            if ($email && Schema::hasTable('crime_department_custom_reports')) {
                $counts['crime_reports'] = CustomCrimeReport::where('created_by', $email)->count();
            }
        } catch (\Throwable $e) {
        }

        try {
            if ($email && Schema::hasTable('crime_department_san_agustin_ai_reports')) {
                $counts['ai_reports'] = SanAgustinAiReport::where('saved_by', $email)->count();
            }
        } catch (\Throwable $e) {
        }

        return $counts;
    }

    /** The last handful of audit entries for this user */
    private function recentActions(?int $userId)
    {
        if (! $userId) {
            return collect();
        }

        try {
            return AuditLog::where('admin_id', $userId)
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(['action_type', 'target_table', 'target_id', 'created_at']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Save the parts of the profile this system owns */
    public function update(Request $request)
    {
        $email = $this->currentEmail();

        if (! $email) {
            return redirect()->route('profile')->with('error', 'You need to be signed in to update your profile.');
        }

        $validated = $request->validate([
            'display_name' => 'nullable|string|max:120',
            'contact_number' => 'nullable|string|max:40',
            'position' => 'nullable|string|max:120',
        ]);

        UserPreference::updateOrCreate(['user_email' => $email], $validated);

        return redirect()->route('profile')->with('success', 'Profile updated.');
    }

    public function settings()
    {
        return view('settings', [
            'identity' => $this->identity(),
            'preferences' => UserPreference::current(),
            'barangays' => \App\Models\Barangay::orderBy('barangay_name')->pluck('barangay_name'),
        ]);
    }

    /**
     * Save preferences. Everything here is read somewhere: the map filters, the
     * street suggestions panel and the alert pages all seed from these.
     */
    public function updateSettings(Request $request)
    {
        $email = $this->currentEmail();

        if (! $email) {
            return redirect()->route('settings')->with('error', 'You need to be signed in to change settings.');
        }

        $validated = $request->validate([
            'default_view_mode' => 'required|in:street-heatmap,markers,heatmap,clusters',
            'default_time_period' => 'required|in:30,90,180,all',
            'default_barangay' => 'nullable|string|max:100',
            'rows_per_page' => 'required|integer|in:10,25,50,100',
            'suggestion_language' => 'required|in:en,tl',
            'alert_sound' => 'nullable|boolean',
            'alert_min_severity' => 'required|in:low,medium,high,critical',
        ]);

        $validated['alert_sound'] = $request->boolean('alert_sound');

        UserPreference::updateOrCreate(['user_email' => $email], $validated);

        return redirect()->route('settings')->with('success', 'Settings saved. The crime pages will open with these from now on.');
    }

    /** Put everything back to the shipped defaults */
    public function resetSettings()
    {
        $email = $this->currentEmail();

        if ($email) {
            UserPreference::where('user_email', $email)->delete();
        }

        return redirect()->route('settings')->with('success', 'Settings restored to their defaults.');
    }
}
