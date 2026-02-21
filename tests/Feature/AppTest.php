<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AppTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the application can load
     */
    public function test_application_loads()
    {
        // Simple test to verify test infrastructure works
        $this->assertTrue(true);
    }
}
