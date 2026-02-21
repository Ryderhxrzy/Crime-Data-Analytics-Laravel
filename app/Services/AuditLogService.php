<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Log an action to the audit log
     *
     * @param string $actionType The type of action performed
     * @param string $targetTable The table that was affected
     * @param int $targetId The ID of the affected record
     * @param array|null $details Additional details about the action
     * @return AuditLog The created audit log entry
     */
    public static function log(
        string $actionType,
        string $targetTable,
        int $targetId,
        ?array $details = null
    ): AuditLog {
        // Try to get admin ID from JWT session first (centralized login), then fallback to Laravel Auth
        $adminId = getUserId() ?? Auth::id() ?? 0;

        return AuditLog::create([
            'admin_id' => $adminId,
            'action_type' => $actionType,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'ip_address' => request()->ip() ?? 'unknown',
            'user_agent' => request()->userAgent() ?? null,
            'details' => $details,
        ]);
    }

    /**
     * Log incident creation
     *
     * @param int $incidentId
     * @param array $details
     * @return AuditLog
     */
    public static function logIncidentInsert(int $incidentId, array $details = []): AuditLog
    {
        return self::log('INSERT_INCIDENT', 'crime_department_crime_incidents', $incidentId, $details);
    }

    /**
     * Log person involved insertion
     *
     * @param int $personId
     * @param int $incidentId
     * @param array $details
     * @return AuditLog
     */
    public static function logPersonInsert(int $personId, int $incidentId, array $details = []): AuditLog
    {
        return self::log('INSERT_PERSON', 'crime_department_persons_involved', $personId, $details);
    }

    /**
     * Log evidence insertion
     *
     * @param int $evidenceId
     * @param int $incidentId
     * @param array $details
     * @return AuditLog
     */
    public static function logEvidenceInsert(int $evidenceId, int $incidentId, array $details = []): AuditLog
    {
        return self::log('INSERT_EVIDENCE', 'crime_department_evidence', $evidenceId, $details);
    }

    /**
     * Log incident view
     *
     * @param int $incidentId
     * @param array $details
     * @return AuditLog
     */
    public static function logIncidentView(int $incidentId, array $details = []): AuditLog
    {
        return self::log('VIEW_INCIDENT', 'crime_department_crime_incidents', $incidentId, $details);
    }
}
