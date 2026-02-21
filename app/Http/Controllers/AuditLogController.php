<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

            // Get unique action types for filter
            $actionTypes = AuditLog::distinct('action_type')->pluck('action_type')->toArray();

            return view('audit-logs', [
                'auditLogs' => $auditLogs,
                'actionTypes' => $actionTypes,
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
}
