<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StaffUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The audit trail. Admin-only (see the admin.only middleware on the routes):
 * staff actions land in it, but only administrators read it.
 */
class AuditLogController extends Controller
{
    /**
     * Display audit logs with filters
     */
    public function index()
    {
        try {
            // Get all audit logs with pagination
            $auditLogs = AuditLog::orderBy('created_at', 'desc')->paginate(25);

            return view('audit-logs', [
                'auditLogs'    => $auditLogs,
                'actionTypes'  => AuditLog::distinct()->orderBy('action_type')->pluck('action_type')->all(),
                'targetTables' => AuditLog::distinct()->orderBy('target_table')->pluck('target_table')->all(),
                'actors'       => $this->actors(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading audit logs', [
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Failed to load audit logs');
        }
    }

    /**
     * Get filtered audit logs via AJAX
     */
    public function getFiltered(Request $request)
    {
        try {
            $query = AuditLog::query();

            // Filter by action type
            if ($request->filled('action_type')) {
                $query->where('action_type', $request->action_type);
            }

            // Filter by admin ID
            if ($request->filled('admin_id')) {
                $query->where('admin_id', $request->admin_id);
            }

            // Who did it. Admin and staff ids come from different tables, so
            // the actor is matched on the email + type the log service stores
            // in `details`. Entries written before that existed carry neither
            // and are treated as admin actions, which is what they were.
            if ($request->filled('actor_type')) {
                $type = $request->actor_type === 'staff' ? 'staff' : 'admin';
                $query->where(function ($q) use ($type) {
                    $q->where('details->actor_type', $type);
                    if ($type === 'admin') {
                        $q->orWhereNull('details->actor_type');
                    }
                });
            }

            if ($request->filled('actor')) {
                $query->where('details->actor_email', $request->actor);
            }

            // Free-text search across the actor's email, the action and the IP
            if ($request->filled('search')) {
                $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($request->search)) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('details->actor_email', 'like', $term)
                      ->orWhere('action_type', 'like', $term)
                      ->orWhere('ip_address', 'like', $term);
                });
            }

            // Filter by date range
            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Filter by target table
            if ($request->filled('target_table')) {
                $query->where('target_table', $request->target_table);
            }

            // Search by IP address
            if ($request->filled('search_ip')) {
                $query->where('ip_address', 'like', '%' . $request->search_ip . '%');
            }

            // Order by created_at descending
            $auditLogs = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $auditLogs,
                'count' => $auditLogs->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching filtered audit logs', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch audit logs'
            ], 500);
        }
    }

    /**
     * Everyone who can appear in the trail, for the "Performed by" filter:
     * admins from the shared table, staff from this app's own.
     *
     * @return array<int, array{email: string, name: string, type: string}>
     */
    private function actors(): array
    {
        $list = [];

        try {
            foreach (User::orderBy('email')->get() as $u) {
                $list[] = ['email' => $u->email, 'name' => $u->full_name ?? $u->name ?? $u->email, 'type' => 'admin'];
            }
        } catch (\Throwable $e) {
            Log::warning('Audit filter: admin list unavailable: ' . $e->getMessage());
        }

        try {
            foreach (StaffUser::orderBy('full_name')->get() as $s) {
                $list[] = ['email' => $s->email, 'name' => $s->full_name ?: $s->email, 'type' => 'staff'];
            }
        } catch (\Throwable $e) {
            Log::warning('Audit filter: staff list unavailable: ' . $e->getMessage());
        }

        return $list;
    }
}
