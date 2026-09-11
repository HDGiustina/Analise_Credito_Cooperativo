<?php

namespace Tests\Feature;

use App\Models\AnaliseCredito;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnaliseCreditoTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $sobrescrever = []): array
    {
        return array_merge([
            'nome' => 'João da Silva',
            'cpf' => '12345678901',
            'renda_mensal' => 8000,
            'tipo_credito' => 'pessoal',
            'valor_solicitado' => 10000,
        ], $sobrescrever);
    }

    private function fakeBureau(int $score): void
    {
        Http::fake([
            '*' => Http::response(['score' => $score], 200),
        ]);
    }

    public function test_aprova_com_score_alto_taxa_reduzida(): void
    {
        $this->fakeBureau(850);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'aprovado'])
            ->assertJsonFragment(['taxa_juros' => '2.90'])
            ->assertJsonFragment(['valor_parcela' => '1123.33']);
    }

    public function test_aprova_com_score_medio_taxa_padrao(): void
    {
        $this->fakeBureau(550);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'aprovado'])
            ->assertJsonFragment(['taxa_juros' => '4.50']);
    }

    public function test_reprova_por_renda_insuficiente(): void
    {
        $this->fakeBureau(850);

        $response = $this->postJson('/api/analise-credito', $this->payload([
            'renda_mensal' => 1000,
        ]));

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'reprovado'])
            ->assertJsonFragment(['motivo_rejeicao' => 'Renda mínima insuficiente']);
    }

    public function test_reprova_por_score_baixo(): void
    {
        $this->fakeBureau(150);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'reprovado'])
            ->assertJsonFragment(['motivo_rejeicao' => 'Score de crédito muito baixo']);
    }

    public function test_reprova_por_comprometimento_de_renda(): void
    {
        $this->fakeBureau(850);

        $response = $this->postJson('/api/analise-credito', $this->payload([
            'renda_mensal' => 3000,
            'valor_solicitado' => 20000,
        ]));

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'reprovado']);

        $this->assertStringStartsWith(
            'Comprometimento de renda superior a',
            $response->json('motivo_rejeicao')
        );
    }

    public function test_responde_sem_crash_quando_bureau_falha(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Erro interno'], 500),
        ]);

        $response = $this->postJson('/api/analise-credito', $this->payload());

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'Serviço de análise indisponível no momento. Tente novamente.']);
    }

    public function test_contrata_analise_aprovada(): void
    {
        $this->fakeBureau(850);

        $analiseId = $this->postJson('/api/analise-credito', $this->payload())->json('id');

        $response = $this->postJson("/api/analise-credito/{$analiseId}/contratar");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'contratado']);

        $this->assertDatabaseHas('analises_credito', [
            'id' => $analiseId,
            'status' => 'contratado',
        ]);
    }

    public function test_cria_cliente_automaticamente_com_cpf_novo(): void
    {
        $this->fakeBureau(850);

        $this->assertDatabaseMissing('clientes', ['cpf' => '98765432109']);

        $response = $this->postJson('/api/analise-credito', $this->payload([
            'cpf' => '98765432109',
        ]));

        $response->assertStatus(201);

        $this->assertDatabaseHas('clientes', ['cpf' => '98765432109']);
        $this->assertNotNull($response->json('cliente_id'));
    }
}
