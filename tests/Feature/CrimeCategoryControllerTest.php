<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CrimeCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_crime_category_list_requires_authentication()
    {
        $response = $this->get('/api/categories');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_categories()
    {
        $user = User::factory()->create();

        CrimeCategory::factory()->count(5)->create();

        $response = $this->actingAs($user)->getJson('/api/categories');

        $response->assertStatus(200);
        $response->assertJsonCount(5);
    }

    public function test_crime_category_has_required_attributes()
    {
        $user = User::factory()->create();

        $category = CrimeCategory::factory()->create([
            'category_name' => 'Theft',
            'color_code' => '#FF0000',
            'icon' => 'fa-lock'
        ]);

        $response = $this->actingAs($user)->getJson("/api/categories/{$category->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'category_name' => 'Theft',
            'color_code' => '#FF0000'
        ]);
    }
}
