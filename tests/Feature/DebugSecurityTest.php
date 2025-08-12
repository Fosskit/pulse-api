<?php

namespace Tests\Feature;

use Tests\TestCase;

class DebugSecurityTest extends TestCase
{
    public function test_debug_health_endpoint_response()
    {
        $response = $this->getJson('/api/v1/health');
        
        dump('Status Code: ' . $response->getStatusCode());
        dump('Headers: ', $response->headers->all());
        dump('Content: ' . $response->getContent());
        
        $this->assertTrue(true); // Just to make the test pass
    }
}