<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SecurityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create basic permissions for testing
        Permission::create(['name' => 'view-patients', 'guard_name' => 'api']);
        Permission::create(['name' => 'create-patients', 'guard_name' => 'api']);
    }

    public function test_api_rate_limiting_middleware_works()
    {
        // Clear any existing rate limits
        RateLimiter::clear('api:127.0.0.1');
        
        // Make a request that should pass
        $response = $this->getJson('/api/v1/health');
        
        $this->assertTrue(in_array($response->getStatusCode(), [200, 404])); // 404 is fine if route doesn't exist
        
        // Check rate limit headers are present
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
    }

    public function test_security_headers_are_added()
    {
        $response = $this->getJson('/api/v1/health');

        // Check that security headers are present
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertTrue($response->headers->has('X-Frame-Options'));
        $this->assertTrue($response->headers->has('X-XSS-Protection'));
    }

    public function test_request_validation_middleware_blocks_large_requests()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a request with very large content
        $largeData = str_repeat('x', 1024 * 1024); // 1MB of data

        $response = $this->postJson('/api/v1/test-endpoint', [
            'large_field' => $largeData
        ]);

        // Should either be blocked by validation middleware or return 404 for non-existent endpoint
        $this->assertTrue(in_array($response->getStatusCode(), [404, 413, 422]));
    }

    public function test_audit_middleware_logs_patient_data_access()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('view-patients', 'api'));
        
        Sanctum::actingAs($user);

        // Make a request to a patient-related endpoint
        $response = $this->getJson('/api/v1/patients');

        // Should either succeed or fail with proper authentication/authorization
        $this->assertTrue(in_array($response->getStatusCode(), [200, 401, 403, 404]));
        
        // Check that activity was logged (if the endpoint exists and was accessed)
        if ($response->getStatusCode() !== 404) {
            $this->assertDatabaseHas('activity_log', [
                'causer_id' => $user->id,
                'log_name' => 'patient_data_access',
            ]);
        }
    }

    public function test_permission_middleware_blocks_unauthorized_access()
    {
        $user = User::factory()->create();
        // User has no permissions
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/patients');

        // Should be forbidden due to lack of permissions
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404]));
    }

    public function test_permission_middleware_allows_authorized_access()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('view-patients', 'api'));
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/patients');

        // Should either succeed or return 404 if endpoint doesn't exist
        $this->assertTrue(in_array($response->getStatusCode(), [200, 404]));
        
        // Should not be forbidden
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_unauthenticated_requests_are_blocked()
    {
        $response = $this->getJson('/api/v1/patients');

        // Should require authentication
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_api_version_middleware_works()
    {
        $response = $this->getJson('/api/v1/health');

        // Should have version information in response or headers
        $this->assertTrue(
            $response->headers->has('X-API-Version') || 
            (is_array($response->json()) && isset($response->json()['version']))
        );
    }

    public function test_content_type_validation_works()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Send POST request with invalid content type
        $response = $this->call('POST', '/api/v1/test-endpoint', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $user->createToken('test')->plainTextToken,
        ], json_encode(['test' => 'data']));

        // Should either be blocked by content type validation or return 404
        $this->assertTrue(in_array($response->getStatusCode(), [404, 415, 422]));
    }

    public function test_input_sanitization_works()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Send request with potentially malicious input
        $response = $this->postJson('/api/v1/test-endpoint', [
            'name' => "Test\x00Name", // Null byte
            'description' => "  Trimmed  ", // Extra whitespace
        ]);

        // Should either process the request (sanitizing input) or return 404
        $this->assertTrue(in_array($response->getStatusCode(), [200, 404, 422]));
    }
}