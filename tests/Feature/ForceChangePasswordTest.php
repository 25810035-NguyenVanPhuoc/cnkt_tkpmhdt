<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ForceChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_response_reports_must_change_password_flag(): void
    {
        $user = User::factory()->create([
            'password' => 'temp-pass-1',
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'temp-pass-1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.must_change_password', true);
    }

    public function test_user_can_change_password_and_flag_clears(): void
    {
        $user = User::factory()->create([
            'password' => 'temp-pass-1',
            'must_change_password' => true,
            'is_active' => true,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/change-password', [
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertFalse($user->must_change_password);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'new-password-1',
        ]);
        $login->assertOk();
        $login->assertJsonPath('user.must_change_password', false);
    }

    public function test_change_password_requires_confirmation_and_min_length(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/change-password', [
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422);
    }
}
