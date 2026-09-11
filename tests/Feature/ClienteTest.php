<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $sobrescrever = []): array
    {
        return array_merge([
            'nome' => 'João da Silva',
            'cpf' => '12345678901',
            'email' => 'joao@example.com',
            'telefone' => '11999999999',
            'renda_mensal' => 3000,
        ], $sobrescrever);
    }

    public function test_cria_cliente_com_dados_validos(): void
    {
        $response = $this->postJson('/api/clientes', $this->payload());

        $response->assertStatus(201)
            ->assertJsonFragment(['cpf' => '12345678901']);

        $this->assertDatabaseHas('clientes', ['cpf' => '12345678901']);
    }

    public function test_falha_ao_criar_sem_campos_obrigatorios(): void
    {
        $response = $this->postJson('/api/clientes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nome', 'cpf', 'email', 'renda_mensal']);
    }

    public function test_falha_ao_criar_com_cpf_duplicado(): void
    {
        $this->postJson('/api/clientes', $this->payload());

        $response = $this->postJson('/api/clientes', $this->payload([
            'email' => 'outro@example.com',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cpf']);
    }

    public function test_falha_ao_criar_com_email_duplicado(): void
    {
        $this->postJson('/api/clientes', $this->payload());

        $response = $this->postJson('/api/clientes', $this->payload([
            'cpf' => '99999999999',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_lista_clientes_paginada(): void
    {
        Cliente::create($this->payload());
        Cliente::create($this->payload([
            'cpf' => '99999999999',
            'email' => 'outro@example.com',
        ]));

        $response = $this->getJson('/api/clientes');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_exibe_cliente_existente(): void
    {
        $cliente = Cliente::create($this->payload());

        $response = $this->getJson("/api/clientes/{$cliente->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['cpf' => '12345678901']);
    }

    public function test_retorna_404_ao_buscar_inexistente(): void
    {
        $response = $this->getJson('/api/clientes/99999');

        $response->assertStatus(404);
    }

    public function test_atualiza_parcialmente_cliente_existente(): void
    {
        $cliente = Cliente::create($this->payload());

        $response = $this->putJson("/api/clientes/{$cliente->id}", [
            'telefone' => '11888888888',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['telefone' => '11888888888']);
    }

    public function test_remove_cliente_existente(): void
    {
        $cliente = Cliente::create($this->payload());

        $response = $this->deleteJson("/api/clientes/{$cliente->id}");

        $response->assertStatus(204);
        $response->assertNoContent();

        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);
    }

    public function test_retorna_404_ao_remover_inexistente(): void
    {
        $response = $this->deleteJson('/api/clientes/99999');

        $response->assertStatus(404);
    }
}
