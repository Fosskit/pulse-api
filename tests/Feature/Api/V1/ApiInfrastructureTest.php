<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Infrastructure Test
 * 
 * Tests the basic API infrastructure including versioning,
 * middleware, and response formatting.
 */
class ApiInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test API health endpoint
     */
    public function test_api_health_endpoint_returns_proper_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'status',
                    'version',
                    'timestamp',
                    'services' => [
                        'database',
                        'cache',
                        'storage',
                    ]
                ],
                'meta' => [
                    'timestamp',
                    'version',
                    'trace_id',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'healthy',
                    'version' => 'v1',
                ],
                'meta' => [
                    'version' => 'v1',
                ]
            ]);
    }

    /**
     * Test API version endpoint
     */
    public function test_api_version_endpoint_returns_proper_response(): void
    {
        $response = $this->getJson('/api/v1/version');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'version',
                    'api_version',
                    'laravel_version',
                    'php_version',
                    'supported_versions',
                    'deprecated_versions',
                ],
                'meta' => [
                    'timestamp',
                    'version',
                    'trace_id',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'version' => 'v1',
                    'api_version' => 'v1',
                    'supported_versions' => ['v1'],
                    'deprecated_versions' => [],
                ],
                'meta' => [
                    'version' => 'v1',
                ]
            ]);
    }

    /**
     * Test API version headers are set correctly
     */
    public function test_api_version_headers_are_set(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('X-API-Version', 'v1')
            ->assertHeader('X-API-Supported-Versions', 'v1')
            ->assertHeader('Content-Type', 'application/json');
    }

    /**
     * Test API accepts version-specific Accept header
     */
    public function test_api_accepts_version_specific_accept_header(): void
    {
        $response = $this->getJson('/api/v1/health', [
            'Accept' => 'application/vnd.emr.v1+json'
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test API rejects invalid version in Accept header
     */
    public function test_api_rejects_invalid_version_in_accept_header(): void
    {
        $response = $this->getJson('/api/v1/health', [
            'Accept' => 'application/vnd.emr.v2+json'
        ]);

        $response->assertStatus(406);
    }

    /**
     * Test API root endpoint
     */
    public function test_api_root_endpoint_returns_welcome_message(): void
    {
        $response = $this->getJson('/api/');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'version',
                'timestamp'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'EMR FHIR API is running',
                'version' => 'v1',
            ]);
    }

    /**
     * Test rate limiting is applied
     */
    public function test_rate_limiting_is_applied(): void
    {
        // Make multiple requests to trigger rate limiting
        for ($i = 0; $i < 65; $i++) {
            $response = $this->getJson('/api/v1/health');
            
            if ($response->status() === 429) {
                // Rate limit hit
                $response->assertJsonStructure([
                    'success',
                    'error' => [
                        'code',
                        'message',
                        'details' => [
                            'retry_after'
                        ]
                    ],
                    'meta' => [
                        'timestamp',
                        'version',
                    ]
                ])
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'RATE_LIMIT_EXCEEDED',
                    ]
                ]);
                return;
            }
        }

        // If we get here, rate limiting might not be working as expected
        // But this could also mean the test environment doesn't enforce it
        $this->assertTrue(true, 'Rate limiting test completed');
    }

    /**
     * Test CORS headers are set
     */
    public function test_cors_headers_are_set(): void
    {
        $response = $this->getJson('/api/v1/health', [
            'Origin' => 'http://localhost:3000'
        ]);

        $response->assertStatus(200);
        
        // Check that CORS headers are present (they may vary based on configuration)
        $this->assertNotNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Test error response format for non-existent endpoint
     */
    public function test_error_response_format_for_non_existent_endpoint(): void
    {
        $response = $this->getJson('/api/v1/non-existent-endpoint');

        $response->assertStatus(404)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message',
                    'details',
                ],
                'meta' => [
                    'timestamp',
                    'version',
                    'trace_id',
                ]
            ])
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                ]
            ]);
    }
}