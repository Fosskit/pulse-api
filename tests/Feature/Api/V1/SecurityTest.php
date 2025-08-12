<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create permissions and roles
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_api_requires_authentication_for_protected_routes()
    {
        $response = $this->getJson('/api/v1/patients');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    public function test_api_rate_limiting_works()
    {
        // Clear any existing rate limits
        RateLimiter::clear('login:' . request()->ip());
        
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // Make multiple failed login attempts to trigger rate limiting
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword'
            ]);
        }

        // Next request should be rate limited
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(429)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'max_attempts',
                        'retry_after_seconds'
                    ]
                ]
            ]);
    }

    public function test_permission_middleware_blocks_unauthorized_access()
    {
        $user = User::factory()->create();
        
        // User without any roles/permissions
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/patients');

        $response->assertStatus(403)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'required_permission'
                    ]
                ]
            ]);
    }

    public function test_user_with_correct_permissions_can_access_resources()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view-patients');
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/patients');

        $response->assertStatus(200);
    }

    public function test_role_based_access_control_works()
    {
        $user = User::factory()->create();
        $doctorRole = Role::findByName('doctor', 'api');
        $user->assignRole($doctorRole);
        
        Sanctum::actingAs($user);

        // Doctor should be able to view patients
        $response = $this->getJson('/api/v1/patients');
        $response->assertStatus(200);

        // Doctor should be able to create visits
        $response = $this->getJson('/api/v1/visits');
        $response->assertStatus(200);
    }

    public function test_receptionist_cannot_access_clinical_data()
    {
        $user = User::factory()->create();
        $receptionistRole = Role::findByName('receptionist', 'api');
        $user->assignRole($receptionistRole);
        
        Sanctum::actingAs($user);

        // Receptionist should not be able to prescribe medications
        $response = $this->postJson('/api/v1/prescriptions/', [
            'patient_id' => 1,
            'medication' => 'Test Medication'
        ]);

        $response->assertStatus(403);
    }

    public function test_api_validates_request_content_type()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-patients');
        
        Sanctum::actingAs($user);

        // Send request without proper content type
        $response = $this->call('POST', '/api/v1/patients', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], json_encode(['name' => 'Test Patient']));

        $response->assertStatus(415)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'allowed_types'
                    ]
                ]
            ]);
    }

    public function test_api_validates_request_size()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-patients');
        
        Sanctum::actingAs($user);

        // Create a large payload (simulate oversized request)
        $largeData = str_repeat('x', 11 * 1024 * 1024); // 11MB

        $response = $this->postJson('/api/v1/patients', [
            'name' => 'Test Patient',
            'large_field' => $largeData
        ]);

        // This should be caught by the validation middleware
        $response->assertStatus(413);
    }

    public function test_security_headers_are_present()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    public function test_audit_logging_for_patient_data_access()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view-patients');
        
        Sanctum::actingAs($user);

        // Access patient data
        $this->getJson('/api/v1/patients');

        // Check that audit log was created
        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $user->id,
            'causer_type' => User::class,
            'log_name' => 'patient_data_access',
        ]);
    }

    public function test_password_validation_enforces_policy()
    {
        // Test weak password
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_token_expiration_is_enforced()
    {
        $user = User::factory()->create();
        
        // Create an expired token
        $token = $user->createToken('test-token');
        $token->accessToken->update([
            'expires_at' => now()->subHour()
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken
        ])->getJson('/api/v1/auth/user');

        $response->assertStatus(401);
    }

    public function test_multiple_failed_login_attempts_are_logged()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // Make failed login attempts
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword'
            ]);
        }

        // Check that failed attempts are logged
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'authentication',
            'description' => 'Failed login attempt',
        ]);
    }

    public function test_sensitive_data_is_not_logged_in_audit()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-patients');
        
        Sanctum::actingAs($user);

        // Create patient with sensitive data
        $this->postJson('/api/v1/patients', [
            'name' => 'Test Patient',
            'password' => 'sensitive-password',
            'api_key' => 'secret-key'
        ]);

        // Check that sensitive data is redacted in logs
        $activity = \Spatie\Activitylog\Models\Activity::where('log_name', 'patient_data_access')->first();
        
        if ($activity) {
            $properties = $activity->properties;
            $this->assertEquals('[REDACTED]', $properties['request_data']['password'] ?? null);
            $this->assertEquals('[REDACTED]', $properties['request_data']['api_key'] ?? null);
        }
    }

    public function test_cors_headers_are_properly_configured()
    {
        $response = $this->options('/api/v1/health', [
            'Origin' => 'https://example.com',
            'Access-Control-Request-Method' => 'GET',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin');
    }

    public function test_api_version_is_properly_handled()
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'version'
            ])
            ->assertJson([
                'version' => 'v1'
            ]);
    }
}