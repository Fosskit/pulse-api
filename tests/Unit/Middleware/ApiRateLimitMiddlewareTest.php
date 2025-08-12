<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ApiRateLimitMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiRateLimitMiddlewareTest extends TestCase
{
    protected ApiRateLimitMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ApiRateLimitMiddleware();
        RateLimiter::clear('test:127.0.0.1');
    }

    public function test_allows_request_within_rate_limit()
    {
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        }, 'api');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_blocks_request_exceeding_rate_limit()
    {
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        // Simulate exceeding rate limit
        $key = 'api:127.0.0.1';
        RateLimiter::hit($key, 60);
        for ($i = 0; $i < 100; $i++) {
            RateLimiter::hit($key, 60);
        }

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        }, 'api');

        $this->assertEquals(429, $response->getStatusCode());
        
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $content['error']['code']);
    }

    public function test_different_limits_for_different_limiters()
    {
        $request = Request::create('/api/v1/auth/login', 'POST');
        $request->setUserResolver(function () {
            return null;
        });

        // Login limiter should have lower limits than API limiter
        $reflection = new \ReflectionClass($this->middleware);
        $method = $reflection->getMethod('getMaxAttempts');
        $method->setAccessible(true);

        $loginLimit = $method->invoke($this->middleware, 'login', $request);
        $apiLimit = $method->invoke($this->middleware, 'api', $request);

        $this->assertLessThan($apiLimit, $loginLimit);
    }

    public function test_authenticated_users_get_higher_limits()
    {
        $user = User::factory()->make(['id' => 1]);
        
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $reflection = new \ReflectionClass($this->middleware);
        $method = $reflection->getMethod('getMaxAttempts');
        $method->setAccessible(true);

        $authenticatedLimit = $method->invoke($this->middleware, 'api', $request);

        $request->setUserResolver(function () {
            return null;
        });

        $unauthenticatedLimit = $method->invoke($this->middleware, 'api', $request);

        $this->assertGreaterThan($unauthenticatedLimit, $authenticatedLimit);
    }

    public function test_adds_rate_limit_headers()
    {
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        }, 'api');

        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
    }

    public function test_includes_retry_after_header_when_rate_limited()
    {
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        // Exceed rate limit
        $key = 'api:127.0.0.1';
        for ($i = 0; $i < 101; $i++) {
            RateLimiter::hit($key, 60);
        }

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        }, 'api');

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Retry-After'));
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));
    }

    public function test_resolves_request_signature_with_user()
    {
        $user = User::factory()->make(['id' => 123]);
        
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $reflection = new \ReflectionClass($this->middleware);
        $method = $reflection->getMethod('resolveRequestSignature');
        $method->setAccessible(true);

        $signature = $method->invoke($this->middleware, $request, 'api');

        $this->assertStringContainsString('123', $signature);
        $this->assertStringContainsString('127.0.0.1', $signature);
    }

    public function test_resolves_request_signature_without_user()
    {
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        $reflection = new \ReflectionClass($this->middleware);
        $method = $reflection->getMethod('resolveRequestSignature');
        $method->setAccessible(true);

        $signature = $method->invoke($this->middleware, $request, 'api');

        $this->assertStringContainsString('127.0.0.1', $signature);
        $this->assertStringStartsWith('api:', $signature); // Should start with limiter name
    }
}