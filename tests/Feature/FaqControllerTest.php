<?php

namespace Tests\Feature;

use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GET /api/faqs - sucesso lista FAQs agrupadas por categoria
     */
    public function test_index_sucesso(): void
    {
        Faq::create([
            'categoria' => 'Geral',
            'pergunta' => 'Pergunta 1?',
            'resposta' => 'Resposta 1',
            'ordem' => 1,
            'ativo' => true,
        ]);
        Faq::create([
            'categoria' => 'Geral',
            'pergunta' => 'Pergunta 2?',
            'resposta' => 'Resposta 2',
            'ordem' => 2,
            'ativo' => true,
        ]);

        $response = $this->getJson('/api/faqs');

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['faqs', 'categorias']);
        $this->assertGreaterThanOrEqual(1, count($response->json('faqs')));
    }

    /**
     * GET /api/faqs - filtro por categoria
     */
    public function test_index_filtro_categoria(): void
    {
        Faq::create([
            'categoria' => 'Pedidos',
            'pergunta' => 'Como rastrear?',
            'resposta' => 'Pelo app.',
            'ordem' => 1,
            'ativo' => true,
        ]);
        Faq::create([
            'categoria' => 'Outra',
            'pergunta' => 'Outra?',
            'resposta' => 'Outra.',
            'ordem' => 1,
            'ativo' => true,
        ]);

        $response = $this->getJson('/api/faqs?categoria=Pedidos');

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);
    }

    /**
     * GET /api/faqs/buscar - sucesso com resultado
     */
    public function test_buscar_sucesso(): void
    {
        Faq::create([
            'categoria' => 'Geral',
            'pergunta' => 'Como alterar senha?',
            'resposta' => 'Em configurações.',
            'ordem' => 1,
            'ativo' => true,
        ]);

        $response = $this->getJson('/api/faqs/buscar?q=senha');

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['faqs', 'total']);
    }

    /**
     * GET /api/faqs/buscar - 400 quando q vazio
     */
    public function test_buscar_q_vazio_retorna_400(): void
    {
        $response = $this->getJson('/api/faqs/buscar?q=');

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Digite uma palavra-chave para buscar']);
    }
}
