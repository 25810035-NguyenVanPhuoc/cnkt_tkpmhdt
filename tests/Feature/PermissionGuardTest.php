<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/categories', ['name' => 'Test', 'slug' => 'test']);

        $response->assertStatus(403);
    }

    public function test_user_with_permission_can_create_category(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('categories.create');
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/categories', ['name' => 'Test', 'slug' => 'test']);

        $response->assertCreated();
    }
}
