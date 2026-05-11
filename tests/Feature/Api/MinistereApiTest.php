<?php

namespace Tests\Feature\Api;

use App\Models\Ministere;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinistereApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ministere = Ministere::create([
            'nom' => 'Test Church',
            'slug' => 'test-church',
            'sous_domaine' => 'test',
            'statut' => 'actif',
            'couleur_primaire' => '#1E3A8A',
            'couleur_secondaire' => '#FFFFFF',
        ]);
    }

    public function test_public_ministere_requires_subdomain(): void
    {
        $response = $this->getJson('/api/public/ministere');
        $response->assertStatus(400);
    }

    public function test_public_ministere_returns_404_for_invalid_subdomain(): void
    {
        $response = $this->getJson('/api/public/ministere?subdomain=nonexistent');
        $response->assertStatus(404);
    }

    public function test_public_ministere_returns_ministry(): void
    {
        $response = $this->getJson('/api/public/ministere?subdomain=test');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'nom',
                    'sous_domaine',
                    'statut',
                ]
            ]);
    }

    public function test_public_articles_requires_subdomain(): void
    {
        $response = $this->getJson('/api/public/articles');
        $response->assertStatus(400);
    }

    public function test_public_pages_requires_subdomain(): void
    {
        $response = $this->getJson('/api/public/pages');
        $response->assertStatus(400);
    }
}