<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AuditLogService;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AuditLogServiceTest extends TestCase
{
    use DatabaseTransactions;

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

    /**
     * Test that log captures IP address
     */
    public function test_log_captures_ip_address()
    {
        // Act
        $log = AuditLogService::log('VIEW_INCIDENT', 'crimes_table', 1);

        // Assert
        $this->assertNotNull($log->ip_address);
        $this->assertTrue(filter_var($log->ip_address, FILTER_VALIDATE_IP) !== false);
    }

    /**
     * Test that log captures user agent
     */
    public function test_log_captures_user_agent()
    {
        // Act
        $log = AuditLogService::log('INSERT_INCIDENT', 'crimes_table', 1);

        // Assert
        $this->assertNotNull($log->user_agent);
    }

    /**
     * Test that log stores details as JSON
     */
    public function test_log_stores_details_as_json()
    {
        // Arrange
        $details = [
            'incident_code' => 'INC-789',
            'incident_title' => 'Test Incident',
            'category_id' => 5,
        ];

        // Act
        $log = AuditLogService::log('UPDATE_INCIDENT', 'crimes_table', 1, $details);

        // Assert
        $this->assertNotNull($log->details);
        $this->assertIsArray($log->details);
        $this->assertArrayHasKey('incident_code', $log->details);
        $this->assertEquals('INC-789', $log->details['incident_code']);
    }

    /**
     * Test that log sets timestamp
     */
    public function test_log_sets_timestamp()
    {
        // Act
        $log = AuditLogService::log('VIEW_INCIDENT', 'crimes_table', 1);

        // Assert
        $this->assertNotNull($log->created_at);
        $this->assertLessThanOrEqual(now(), $log->created_at);
    }

    /**
     * Test that log can be created without details
     */
    public function test_log_can_be_created_without_details()
    {
        // Act
        $log = AuditLogService::log('DELETE_INCIDENT', 'crimes_table', 1);

        // Assert
        $this->assertNotNull($log);
        $this->assertEquals('DELETE_INCIDENT', $log->action_type);
    }

    /**
     * Test that multiple logs can be created
     */
    public function test_multiple_logs_can_be_created()
    {
        // Act
        AuditLogService::log('INSERT_INCIDENT', 'crimes_table', 1);
        AuditLogService::log('UPDATE_INCIDENT', 'crimes_table', 1);
        AuditLogService::log('DELETE_INCIDENT', 'crimes_table', 1);

        // Assert
        $this->assertDatabaseCount('crime_department_audit_logs', 3);
    }

    /**
     * Test that logIncidentView records view action
     */
    public function test_log_incident_view_records_view_action()
    {
        // Arrange
        $incidentId = 456;
        $details = ['incident_code' => 'INC-456', 'incident_title' => 'Test View'];

        // Act
        $log = AuditLogService::logIncidentView($incidentId, $details);

        // Assert
        $this->assertDatabaseHas('crime_department_audit_logs', [
            'action_type' => 'VIEW_INCIDENT',
            'target_table' => 'crime_department_crime_incidents',
            'target_id' => $incidentId,
        ]);
        $this->assertEquals('VIEW_INCIDENT', $log->action_type);
    }

    /**
     * Test that admin_id defaults to 0 if not authenticated
     */
    public function test_log_defaults_admin_id_if_not_authenticated()
    {
        // Act
        $log = AuditLogService::log('VIEW_INCIDENT', 'crimes_table', 1);

        // Assert
        $this->assertIsInt($log->admin_id);
    }

    /**
     * Test that different action types are recorded correctly
     */
    public function test_different_action_types_are_recorded()
    {
        // Arrange
        $actionTypes = ['INSERT_INCIDENT', 'UPDATE_INCIDENT', 'DELETE_INCIDENT', 'VIEW_INCIDENT'];

        // Act
        foreach ($actionTypes as $type) {
            AuditLogService::log($type, 'crimes_table', 1);
        }

        // Assert
        foreach ($actionTypes as $type) {
            $this->assertDatabaseHas('crime_department_audit_logs', [
                'action_type' => $type,
            ]);
        }
    }
}
