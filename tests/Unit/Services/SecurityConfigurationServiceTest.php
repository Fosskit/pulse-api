<?php

namespace Tests\Unit\Services;

use App\Services\SecurityConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityConfigurationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SecurityConfigurationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SecurityConfigurationService();
    }

    public function test_get_api_security_config_returns_expected_structure()
    {
        $config = $this->service->getApiSecurityConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('rate_limits', $config);
        $this->assertArrayHasKey('token_expiration', $config);
        $this->assertArrayHasKey('password_policy', $config);
        $this->assertArrayHasKey('session_security', $config);
        $this->assertArrayHasKey('audit_settings', $config);
    }

    public function test_get_permission_matrix_returns_all_roles()
    {
        $matrix = $this->service->getPermissionMatrix();

        $expectedRoles = [
            'super-admin',
            'admin',
            'doctor',
            'nurse',
            'technician',
            'pharmacist',
            'receptionist'
        ];

        foreach ($expectedRoles as $role) {
            $this->assertArrayHasKey($role, $matrix);
            $this->assertIsArray($matrix[$role]);
        }
    }

    public function test_super_admin_has_wildcard_permission()
    {
        $matrix = $this->service->getPermissionMatrix();

        $this->assertContains('*', $matrix['super-admin']);
    }

    public function test_has_permission_works_for_wildcard()
    {
        $this->assertTrue($this->service->hasPermission('super-admin', 'any-permission'));
    }

    public function test_has_permission_works_for_specific_permissions()
    {
        $this->assertTrue($this->service->hasPermission('doctor', 'view-patients'));
        $this->assertTrue($this->service->hasPermission('doctor', 'prescribe-medications'));
        $this->assertFalse($this->service->hasPermission('nurse', 'prescribe-medications'));
    }

    public function test_has_permission_returns_false_for_unknown_role()
    {
        $this->assertFalse($this->service->hasPermission('unknown-role', 'view-patients'));
    }

    public function test_get_security_headers_returns_proper_headers()
    {
        $headers = $this->service->getSecurityHeaders();

        $expectedHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection',
            'Referrer-Policy',
            'Content-Security-Policy',
            'Strict-Transport-Security',
            'Permissions-Policy'
        ];

        foreach ($expectedHeaders as $header) {
            $this->assertArrayHasKey($header, $headers);
        }
    }

    public function test_validate_password_enforces_minimum_length()
    {
        $result = $this->service->validatePassword('123');

        $this->assertFalse($result['valid']);
        $this->assertContains('Password must be at least 8 characters long', $result['errors']);
    }

    public function test_validate_password_enforces_uppercase_requirement()
    {
        $result = $this->service->validatePassword('password123');

        $this->assertFalse($result['valid']);
        $this->assertContains('Password must contain at least one uppercase letter', $result['errors']);
    }

    public function test_validate_password_enforces_lowercase_requirement()
    {
        $result = $this->service->validatePassword('PASSWORD123');

        $this->assertFalse($result['valid']);
        $this->assertContains('Password must contain at least one lowercase letter', $result['errors']);
    }

    public function test_validate_password_enforces_number_requirement()
    {
        $result = $this->service->validatePassword('Password');

        $this->assertFalse($result['valid']);
        $this->assertContains('Password must contain at least one number', $result['errors']);
    }

    public function test_validate_password_accepts_valid_password()
    {
        $result = $this->service->validatePassword('Password123');

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_clear_cache_removes_cached_data()
    {
        // First call to cache the data
        $this->service->getApiSecurityConfig();
        $this->service->getPermissionMatrix();

        // Verify cache exists
        $this->assertTrue(Cache::has('api_security_config'));
        $this->assertTrue(Cache::has('permission_matrix'));

        // Clear cache
        $this->service->clearCache();

        // Verify cache is cleared
        $this->assertFalse(Cache::has('api_security_config'));
        $this->assertFalse(Cache::has('permission_matrix'));
    }

    public function test_rate_limits_configuration_is_reasonable()
    {
        $config = $this->service->getApiSecurityConfig();
        $rateLimits = $config['rate_limits'];

        // Login should be more restrictive than general API
        $this->assertLessThan($rateLimits['api']['max_attempts'], $rateLimits['login']['max_attempts']);
        
        // Registration should be more restrictive than login
        $this->assertLessThan($rateLimits['login']['max_attempts'], $rateLimits['register']['max_attempts']);
        
        // Sensitive operations should have reasonable limits
        $this->assertGreaterThan(0, $rateLimits['sensitive']['max_attempts']);
        $this->assertLessThan(100, $rateLimits['sensitive']['max_attempts']);
    }

    public function test_token_expiration_configuration_is_reasonable()
    {
        $config = $this->service->getApiSecurityConfig();
        $tokenExpiration = $config['token_expiration'];

        // Access tokens should expire before refresh tokens
        $this->assertLessThan($tokenExpiration['refresh_token'], $tokenExpiration['access_token']);
        
        // Remember tokens should last longer than regular tokens
        $this->assertGreaterThan($tokenExpiration['refresh_token'], $tokenExpiration['remember_token']);
    }

    public function test_password_policy_is_secure()
    {
        $config = $this->service->getApiSecurityConfig();
        $policy = $config['password_policy'];

        // Minimum length should be at least 8
        $this->assertGreaterThanOrEqual(8, $policy['min_length']);
        
        // Should require uppercase and lowercase
        $this->assertTrue($policy['require_uppercase']);
        $this->assertTrue($policy['require_lowercase']);
        
        // Should require numbers
        $this->assertTrue($policy['require_numbers']);
        
        // Password age should be reasonable
        $this->assertGreaterThan(30, $policy['max_age_days']);
        $this->assertLessThan(365, $policy['max_age_days']);
    }
}