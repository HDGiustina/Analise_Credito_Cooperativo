<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_respeita_header_x_locale(): void
    {
        Http::fake([
            '*' => Http::response(['score' => 850], 200),
        ]);

        $payload = [
            'nome' => 'João da Silva',
            'cpf' => '12345678901',
            'renda_mensal' => 1000,
            'tipo_credito' => 'pessoal',
            'valor_solicitado' => 2000,
        ];

        $this->postJson('/api/analise-credito', $payload)
            ->assertJsonFragment(['motivo_rejeicao' => 'Renda mínima insuficiente']);

        $this->withHeader('X-Locale', 'en')
            ->postJson('/api/analise-credito', $payload)
            ->assertJsonFragment(['motivo_rejeicao' => 'Insufficient minimum income']);
    }

    public function test_rota_locale_grava_cookie_e_redireciona(): void
    {
        $response = $this->get('/locale/en');

        $response->assertRedirect();
        $response->assertCookie('locale', 'en');
    }
}
