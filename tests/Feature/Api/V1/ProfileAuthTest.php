<?php

namespace Tests\Feature\Api\V1;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_own_email_and_name(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'name' => 'Old Name',
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
            'name' => 'New Name',
        ]);
    }

    public function test_user_can_update_password_with_current_password(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create([
            'password' => 'password',
            'location_id' => $location->id,
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'new-secret-pass',
            'location_id' => $location->id,
        ])->assertOk();
    }
}
