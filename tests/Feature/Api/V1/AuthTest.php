<?php

namespace Tests\Feature\Api\V1;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create([
            'email' => 'user1@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user1@example.com',
            'password' => 'password',
            'location_id' => $location->id,
            'device_name' => 'test-device',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['token', 'token_type', 'user' => ['id', 'name', 'email', 'location_id']],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame($user->id, $response->json('data.user.id'));
        $this->assertSame($location->id, $response->json('data.user.location_id'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $location = Location::factory()->create();
        User::factory()->create([
            'email' => 'user1@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user1@example.com',
            'password' => 'wrong-password',
            'location_id' => $location->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $location = Location::factory()->create();
        User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
            'location_id' => $location->id,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_fetch_me(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
