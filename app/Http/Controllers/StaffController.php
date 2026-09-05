<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StaffUser;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Staff Management (admins only).
 *
 * Admins create staff accounts here. Each new account gets a generated
 * temporary password that is emailed to the staff member, who must replace it
 * on first sign-in (see RequirePasswordChange). Admin accounts themselves stay
 * in the centralized portal and are not managed here.
 */
class StaffController extends Controller
{
    public function index()
    {
        if (!Schema::hasTable('crime_department_staff')) {
            return view('staff.index', [
                'staff'      => collect(),
                'stats'      => ['total' => 0, 'active' => 0, 'pending' => 0, 'recent' => 0],
                'charts'     => ['status' => [0, 0, 0], 'monthly' => ['labels' => [], 'values' => []], 'positions' => ['labels' => [], 'values' => []]],
                'tableReady' => false,
            ]);
        }

        $staff = StaffUser::orderBy('full_name')->get();

        $stats = [
            'total'   => $staff->count(),
            'active'  => $staff->where('is_active', true)->count(),
            'pending' => $staff->where('is_active', true)->where('must_change_password', true)->count(),
            'recent'  => $staff->filter(fn ($s) => $s->last_login && $s->last_login->gte(now()->subDays(7)))->count(),
        ];

        // Accounts created per month, last six months (older months collapse into the first bucket)
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $monthly = [
            'labels' => $months->map(fn ($m) => $m->format('M Y'))->values()->all(),
            'values' => $months->map(fn ($m) => $staff->filter(
                fn ($s) => $s->created_at && $s->created_at->format('Y-m') === $m->format('Y-m')
            )->count())->values()->all(),
        ];

        $positions = $staff->groupBy(fn ($s) => trim((string) $s->position) !== '' ? $s->position : 'Unassigned')
            ->map->count()->sortDesc()->take(6);

        return view('staff.index', [
            'staff'      => $staff,
            'stats'      => $stats,
            'charts'     => [
                'status'    => [
                    $staff->where('is_active', true)->where('must_change_password', false)->count(),
                    $stats['pending'],
                    $staff->where('is_active', false)->count(),
                ],
                'monthly'   => $monthly,
                'positions' => ['labels' => $positions->keys()->values()->all(), 'values' => $positions->values()->all()],
            ],
            'tableReady' => true,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name'      => 'required|string|max:150',
            'email'          => ['required', 'email', 'max:150', Rule::unique('crime_department_staff', 'email')],
            'position'       => 'nullable|string|max:120',
            'contact_number' => 'nullable|string|max:40',
        ]);

        $email = mb_strtolower(trim($validated['email']));

        // The login form checks admins first, so a staff row under an admin
        // address would never be reachable.
        if ($this->isAdminEmail($email)) {
            return back()->withErrors(['email' => 'That email already belongs to an administrator account.'])->withInput();
        }

        $temporary = $this->temporaryPassword();

        $staff = StaffUser::create([
            'full_name'            => trim($validated['full_name']),
            'email'                => $email,
            'password_hash'        => password_hash($temporary, PASSWORD_BCRYPT),
            'position'             => $validated['position'] ?? null,
            'contact_number'       => $validated['contact_number'] ?? null,
            'is_active'            => true,
            'must_change_password' => true,
            'created_by'           => currentAccount()['email'] ?? null,
        ]);

        $sent = $this->sendCredentials($staff, $temporary, 'welcome');

        $this->audit('CREATE_STAFF', $staff, [
            'email'             => $staff->email,
            'full_name'         => $staff->full_name,
            'credentials_email' => $sent ? 'sent' : 'failed',
        ]);

        return $this->afterCredentials($staff, $temporary, $sent,
            "Staff account for {$staff->full_name} created.");
    }

    public function update(Request $request, int $id)
    {
        $staff = StaffUser::findOrFail($id);

        $validated = $request->validate([
            'full_name'      => 'required|string|max:150',
            'position'       => 'nullable|string|max:120',
            'contact_number' => 'nullable|string|max:40',
        ]);

        $staff->update([
            'full_name'      => trim($validated['full_name']),
            'position'       => $validated['position'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
        ]);

        $this->audit('UPDATE_STAFF', $staff, ['email' => $staff->email]);

        return redirect()->route('staff.index')->with('success', "Details for {$staff->full_name} updated.");
    }

    /** Issue a fresh temporary password and email it again */
    public function resetPassword(int $id)
    {
        $staff = StaffUser::findOrFail($id);
        $temporary = $this->temporaryPassword();

        $staff->update([
            'password_hash'        => password_hash($temporary, PASSWORD_BCRYPT),
            'must_change_password' => true,
            'attempt_count'        => 0,
            'locked_until'         => null,
        ]);

        $sent = $this->sendCredentials($staff, $temporary, 'reset');

        $this->audit('RESET_STAFF_PASSWORD', $staff, [
            'email'             => $staff->email,
            'credentials_email' => $sent ? 'sent' : 'failed',
        ]);

        return $this->afterCredentials($staff, $temporary, $sent,
            "A new temporary password was issued for {$staff->full_name}.");
    }

    public function toggle(int $id)
    {
        $staff = StaffUser::findOrFail($id);
        $staff->update(['is_active' => !$staff->is_active]);

        $this->audit($staff->is_active ? 'ACTIVATE_STAFF' : 'DEACTIVATE_STAFF', $staff, ['email' => $staff->email]);

        return redirect()->route('staff.index')->with('success',
            $staff->full_name . ' is now ' . ($staff->is_active ? 'active and can sign in.' : 'deactivated and can no longer sign in.'));
    }

    public function destroy(int $id)
    {
        $staff = StaffUser::findOrFail($id);
        $name = $staff->full_name;
        $email = $staff->email;
        $staff->delete();

        try {
            AuditLogService::log('DELETE_STAFF', 'crime_department_staff', $id, ['email' => $email, 'full_name' => $name]);
        } catch (\Throwable $e) {
            Log::warning('Could not audit staff deletion: ' . $e->getMessage());
        }

        return redirect()->route('staff.index')->with('success', "Staff account for {$name} deleted.");
    }

    // ------------------------------------------------------------------

    private function isAdminEmail(string $email): bool
    {
        try {
            return User::where('email', $email)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** 12 characters, letters and digits only, so it survives being typed from an email */
    private function temporaryPassword(): string
    {
        return Str::password(12, letters: true, numbers: true, symbols: false, spaces: false);
    }

    private function sendCredentials(StaffUser $staff, string $temporary, string $kind): bool
    {
        try {
            Mail::send('emails.staff-credentials', [
                'staff'     => $staff,
                'password'  => $temporary,
                'kind'      => $kind,
                'loginUrl'  => route('login'),
                'issuedBy'  => currentAccount()['email'] ?? 'the administrator',
            ], function ($message) use ($staff, $kind) {
                $message->to($staff->email, $staff->full_name)
                    ->subject($kind === 'reset'
                        ? 'Your Crime Data Analytics password has been reset'
                        : 'Your Crime Data Analytics staff account');
            });

            $staff->update(['credentials_sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to email staff credentials', ['email' => $staff->email, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * When the email went out, the admin only sees a confirmation. When it did
     * not, the temporary password is shown once so the admin can hand it over
     * another way instead of leaving the staff member locked out.
     */
    private function afterCredentials(StaffUser $staff, string $temporary, bool $sent, string $message)
    {
        $redirect = redirect()->route('staff.index');

        if ($sent) {
            return $redirect->with('success', $message . " The login details were emailed to {$staff->email}.");
        }

        return $redirect
            ->with('warning', $message . " The email to {$staff->email} could not be sent, so the temporary password is shown below once. Please pass it on securely.")
            ->with('temporary_credentials', ['email' => $staff->email, 'password' => $temporary]);
    }

    private function audit(string $action, StaffUser $staff, array $details = []): void
    {
        try {
            AuditLogService::log($action, 'crime_department_staff', (int) $staff->id, $details);
        } catch (\Throwable $e) {
            Log::warning("Could not audit {$action}: " . $e->getMessage());
        }
    }
}
