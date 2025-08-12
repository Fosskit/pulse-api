<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email'
                    ],
                    'token',
                    'expires_at'
                ]
            ]);
    }

    public function test_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials'
            ]);
    }

    public function test_can_register_new_user()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email'
                    ],
                    'token'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
    }

    public function test_validates_registration_data()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password'
            ]);
    }

    public function test_can_get_authenticated_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'created_at'
                ]
            ])
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ]);
    }

    public function test_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out'
            ]);
    }

    public function test_can_refresh_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'expires_at'
                ]
            ]);
    }

    public function test_can_request_password_reset()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com'
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'test@example.com'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password reset link sent to your email'
            ]);
    }

    public function test_validates_password_reset_email()
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_can_reset_password()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com'
        ]);

        // Generate a password reset token
        $token = app('auth.password.broker')->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password has been reset successfully'
            ]);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_validates_password_reset_data()
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'email',
                'password'
            ]);
    }

    public function test_can_get_user_devices()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/devices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => []
            ]);
    }

    public function test_can_disconnect_device()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/devices/disconnect', [
                'token_id' => $token->accessToken->id
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Device disconnected successfully'
            ]);
    }

    public function test_login_is_rate_limited()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // Make multiple failed login attempts
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword'
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(429); // Too Many Requests
    }

    public function test_requires_authentication_for_protected_routes()
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    public function test_validates_login_data()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => '',
            'password' => ''
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'email',
                'password'
            ]);
    }

    public function test_prevents_duplicate_email_registration()
    {
        User::factory()->create([
            'email' => 'existing@example.com'
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}