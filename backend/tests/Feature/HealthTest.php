<?php
namespace Tests\Feature;
use Tests\TestCase;
final class HealthTest extends TestCase {
    public function test_health_matches_contract(): void {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status'=>'ok']);
    }
    public function test_protected_route_requires_token(): void {
        $this->getJson('/api/v1/books')->assertUnauthorized()->assertJsonStructure(['code','message','details']);
    }
    public function test_api_not_found_uses_uniform_error_body(): void {
        $this->getJson('/api/v1/unknown')->assertNotFound()->assertJsonStructure(['code','message','details']);
    }
}
