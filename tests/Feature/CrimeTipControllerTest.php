<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeTip;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CrimeTipControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tip_submission_requires_valid_crime_type()
    {
        $response = $this->post('/submit-tip', [
            'crime_type' => '',
            'location' => 'Test Location',
            'tip_content' => 'This is a test tip with sufficient content'
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_tip_submission_requires_location()
    {
        $response = $this->post('/submit-tip', [
            'crime_type' => 'Theft',
            'location' => '',
            'tip_content' => 'This is a test tip with sufficient content'
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_tip_content_must_meet_minimum_length()
    {
        $response = $this->post('/submit-tip', [
            'crime_type' => 'Theft',
            'location' => 'Test Location',
            'tip_content' => 'Short'
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_crime_tip_can_be_created()
    {
        $tip = CrimeTip::create([
            'crime_type' => 'Robbery',
            'location' => 'Main Street',
            'details' => 'This is a detailed description of the crime tip',
            'status' => 'open'
        ]);

        $this->assertDatabaseHas('crime_tips', [
            'crime_type' => 'Robbery',
            'location' => 'Main Street'
        ]);
    }
}
