<?php

namespace Tests\Feature\Api;

use App\Models\Ministere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_valid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'wrongpassword',
        ]);
        
        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_login_rate_limiting(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/login', [
                'email' => 'test@test.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/login', [
            'email' => 'test@test.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }

    public function test_register_creates_ministry_and_admin(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Admin',
            'email' => 'newchurch@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'ministere_nom' => 'New Church',
            'ministere_type' => 'eglise',
            'sous_domaine' => 'newchurch',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'token',
                'user',
                'ministere' => ['id', 'nom', 'sous_domaine'],
            ]);
    }

    public function test_register_validates_subdomain_uniqueness(): void
    {
        Ministere::create([
            'nom' => 'Existing Church',
            'slug' => 'existing',
            'sous_domaine' => 'existing',
            'statut' => 'actif',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Admin',
            'email' => 'new@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'ministere_nom' => 'New Church',
            'ministere_type' => 'eglise',
            'sous_domaine' => 'existing',
        ]);

        $response->assertStatus(422);
    }
}