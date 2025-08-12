<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

/**
 * Security Configuration Service
 * 
 * Manages security-related configurations and provides
 * centralized security policy management.
 */
class SecurityConfigurationService
{
    /**
     * Get security configuration for API endpoints.
     */
    public function getApiSecurityConfig(): array
    {
        return Cache::remember('api_security_config', 3600, function () {
            return [
                'rate_limits' => [
                    'login' => ['max_attempts' => 5, 'decay_minutes' => 15],
                    'register' => ['max_attempts' => 3, 'decay_minutes' => 60],
                    'password_reset' => ['max_attempts' => 5, 'decay_minutes' => 60],
                    'api' => ['max_attempts' => 1000, 'decay_minutes' => 1],
                    'uploads' => ['max_attempts' => 50, 'decay_minutes' => 60],
                    'sensitive' => ['max_attempts' => 20, 'decay_minutes' => 60],
                ],
                'token_expiration' => [
                    'access_token' => 1440, // 24 hours
                    'refresh_token' => 10080, // 7 days
                    'remember_token' => 43200, // 30 days
                ],
                'password_policy' => [
                    'min_length' => 8,
                    'require_uppercase' => true,
                    'require_lowercase' => true,
                    'require_numbers' => true,
                    'require_symbols' => false,
                    'max_age_days' => 90,
                ],
                'session_security' => [
                    'secure_cookies' => env('APP_ENV') === 'production',
                    'http_only_cookies' => true,
                    'same_site_cookies' => 'strict',
                    'session_timeout' => 120, // 2 hours
                ],
                'audit_settings' => [
                    'log_all_requests' => env('AUDIT_LOG_ALL_REQUESTS', false),
                    'log_patient_data_access' => true,
                    'log_failed_auth_attempts' => true,
                    'retention_days' => 365,
                ],
            ];
        });
    }
    
    /**
     * Get permission matrix for different roles.
     */
    public function getPermissionMatrix(): array
    {
        return Cache::remember('permission_matrix', 3600, function () {
            return [
                'super-admin' => ['*'], // All permissions
                'admin' => [
                    'view-patients', 'create-patients', 'edit-patients',
                    'view-visits', 'create-visits', 'edit-visits',
                    'view-encounters', 'create-encounters', 'edit-encounters',
                    'view-observations', 'create-observations', 'edit-observations',
                    'view-dashboard', 'manage-users', 'view-reports',
                    'export-data', 'import-data', 'manage-taxonomy',
                    'view-invoices', 'create-invoices', 'edit-invoices',
                    'view-schedules', 'create-appointments', 'edit-appointments'
                ],
                'doctor' => [
                    'view-patients', 'create-patients', 'edit-patients',
                    'view-visits', 'create-visits', 'edit-visits',
                    'view-encounters', 'create-encounters', 'edit-encounters',
                    'view-observations', 'create-observations', 'edit-observations',
                    'record-vitals', 'prescribe-medications',
                    'view-lab-results', 'create-lab-orders',
                    'view-imaging', 'create-imaging-orders',
                    'view-dashboard', 'view-reports',
                    'view-invoices', 'create-invoices',
                    'view-schedules', 'create-appointments', 'edit-appointments'
                ],
                'nurse' => [
                    'view-patients', 'edit-patients',
                    'view-visits', 'edit-visits',
                    'view-encounters', 'create-encounters', 'edit-encounters',
                    'view-observations', 'create-observations', 'edit-observations',
                    'record-vitals', 'administer-medications',
                    'view-lab-results', 'view-imaging',
                    'view-dashboard',
                    'view-schedules', 'create-appointments', 'edit-appointments'
                ],
                'technician' => [
                    'view-patients',
                    'view-visits',
                    'view-encounters',
                    'view-observations', 'create-observations', 'edit-observations',
                    'view-lab-results', 'view-imaging',
                    'view-dashboard'
                ],
                'pharmacist' => [
                    'view-patients',
                    'view-visits',
                    'view-encounters',
                    'view-observations',
                    'administer-medications',
                    'view-dashboard'
                ],
                'receptionist' => [
                    'view-patients', 'create-patients', 'edit-patients',
                    'view-visits', 'create-visits',
                    'view-dashboard',
                    'view-schedules', 'create-appointments', 'edit-appointments', 'cancel-appointments',
                    'view-invoices', 'create-invoices', 'process-payments'
                ],
            ];
        });
    }
    
    /**
     * Check if a user has permission for a specific action.
     */
    public function hasPermission(string $role, string $permission): bool
    {
        $matrix = $this->getPermissionMatrix();
        
        if (!isset($matrix[$role])) {
            return false;
        }
        
        $rolePermissions = $matrix[$role];
        
        // Check for wildcard permission
        if (in_array('*', $rolePermissions)) {
            return true;
        }
        
        return in_array($permission, $rolePermissions);
    }
    
    /**
     * Get security headers for API responses.
     */
    public function getSecurityHeaders(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; media-src 'self'; object-src 'none'; child-src 'none'; worker-src 'none'; frame-ancestors 'none'; form-action 'self'; base-uri 'self';",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), speaker=(), vibrate=(), fullscreen=(self), sync-xhr=()',
        ];
    }
    
    /**
     * Validate password against security policy.
     */
    public function validatePassword(string $password): array
    {
        $config = $this->getApiSecurityConfig();
        $policy = $config['password_policy'];
        $errors = [];
        
        if (strlen($password) < $policy['min_length']) {
            $errors[] = "Password must be at least {$policy['min_length']} characters long";
        }
        
        if ($policy['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if ($policy['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if ($policy['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if ($policy['require_symbols'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Clear security configuration cache.
     */
    public function clearCache(): void
    {
        Cache::forget('api_security_config');
        Cache::forget('permission_matrix');
    }
}