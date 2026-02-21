<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AuditLogService;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that log creates an audit log entry
     */
    public function test_log_creates_audit_log_entry()
    {
        // Arrange
        $actionType = 'VIEW_INCIDENT';
        $targetTable = 'crime_department_crime_incidents';
        $targetId = 123;
        $details = ['incident_code' => 'INC-001'];

        // Act
        $log = AuditLogService::log($actionType, $targetTable, $targetId, $details);

        // Assert
        $this->assertNotNull($log);
        $this->assertEquals($actionType, $log->action_type);
        $this->assertEquals($targetTable, $log->target_table);
        $this->assertEquals($targetId, $log->target_id);
        $this->assertDatabaseHas('crime_department_audit_logs', [
            'action_type' => $actionType,
            'target_table' => $targetTable,
            'target_id' => $targetId,
        ]);
    }

}
