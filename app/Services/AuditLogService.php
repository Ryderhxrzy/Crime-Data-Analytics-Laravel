<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'created_at' => now(), // Explicitly set timestamp in Asia/Manila timezone
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

    /**
     * Log when user requests to send decryption OTP
     *
     * @param int $incidentId
     * @param array $details
     * @return AuditLog
     */
    public static function logSendDecryptOtp(int $incidentId, array $details = []): AuditLog
    {
        return self::log('SEND_DECRYPT_OTP', 'crime_department_crime_incidents', $incidentId, $details);
    }

    /**
     * Log successful OTP verification for decryption
     *
     * @param int $incidentId
     * @param array $details
     * @return AuditLog
     */
    public static function logVerifyDecryptOtpSuccess(int $incidentId, array $details = []): AuditLog
    {
        return self::log('VERIFY_DECRYPT_OTP_SUCCESS', 'crime_department_crime_incidents', $incidentId, $details);
    }

    /**
     * Log failed OTP verification for decryption
     *
     * @param int $incidentId
     * @param array $details
     * @return AuditLog
     */
    public static function logVerifyDecryptOtpFailed(int $incidentId, array $details = []): AuditLog
    {
        return self::log('VERIFY_DECRYPT_OTP_FAILED', 'crime_department_crime_incidents', $incidentId, $details);
    }

    /**
     * Log successful data decryption
     *
     * @param int $incidentId
     * @param array $details
     * @return AuditLog
     */
    public static function logDecryptDataSuccess(int $incidentId, array $details = []): AuditLog
    {
        return self::log('DECRYPT_DATA_SUCCESS', 'crime_department_crime_incidents', $incidentId, $details);
    }

    /**
     * Log data decryption error
     *
     * @param int $incidentId
     * @param array $details
     * @return AuditLog
     */
    public static function logDecryptDataError(int $incidentId, array $details = []): AuditLog
    {
        return self::log('DECRYPT_DATA_ERROR', 'crime_department_crime_incidents', $incidentId, $details);
    }
}
