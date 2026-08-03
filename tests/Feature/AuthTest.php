<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_user_can_login_with_correct_credentials()
    {
        $response = $this->withHeaders(['Referer' => 'http://localhost:3000'])->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'user']);
                 
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_user_cannot_login_with_incorrect_password()
    {
        $response = $this->withHeaders(['Referer' => 'http://localhost:3000'])->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'message' => 'Kredensial tidak valid'
                 ]);
                 
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_5_failed_attempts()
    {
        // Fail 5 times
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['Referer' => 'http://localhost:3000'])->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        // 6th attempt should be rate limited
        $response = $this->withHeaders(['Referer' => 'http://localhost:3000'])->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429);
    }

    public function test_rate_limiter_is_cleared_on_successful_login()
    {
        // Fail 3 times
        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders(['Referer' => 'http://localhost:3000'])->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        // 4th attempt is successful
        $this->withHeaders(['Referer' => 'http://localhost:3000'])->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        // Fail 5 times again to prove counter was reset
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['Referer' => 'http://localhost:3000'])->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(401);
        }

        // 6th attempt after reset should be rate limited
        $this->withHeaders(['Referer' => 'http://localhost:3000'])->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ])->assertStatus(429);
    }

    public function test_protected_routes_require_authentication()
    {
        // Try accessing a protected route without login
        $response = $this->withHeaders(['Referer' => 'http://localhost:3000'])->getJson('/api/projects');
        $response->assertStatus(401);

        // Login and try again
        $this->actingAs($this->user);
        
        $response = $this->withHeaders(['Referer' => 'http://localhost:3000'])->getJson('/api/projects');
        $response->assertStatus(200);
    }
}
