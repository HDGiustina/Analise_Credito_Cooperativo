<?php

namespace App\Http\Controllers;

use App\Enums\StatusAnalise;
use App\Exceptions\BureauIndisponivelException;
use App\Http\Requests\SolicitarAnaliseRequest;
use App\Models\AnaliseCredito;
use App\Models\Cliente;
use App\Services\AnaliseCreditoService;
use App\Services\BureauService;
use Illuminate\Http\Request;

class AnaliseCreditoController extends Controller
{
    /**
     * Solicita uma nova análise de crédito.
     *
     * POST /api/analise-credito
     *
     * Campos esperados no body (JSON):
     *  - nome: string, obrigatório
     *  - cpf: string, obrigatório (11 dígitos)
     *  - renda_mensal: numeric, obrigatório
     *  - tipo_credito: string, obrigatório (pessoal | imobiliario | automotivo)
     *  - valor_solicitado: numeric, obrigatório
     *
     * Fluxo esperado:
     *  1. Validar os dados de entrada.
     *  2. Persistir a análise no banco com status 'pendente'.
     *  3. Consultar a API do Bureau de Crédito (GET /api/mock/bureau/{cpf}) via Http::.
     *  4. Tratar falhas de comunicação com o Bureau (timeout, HTTP 500, resposta malformada).
     *  5. Aplicar as regras de negócio (renda mínima, faixas de score, comprometimento de renda).
     *  6. Atualizar e retornar a análise persistida com o resultado final.
     *
     * @param  \App\Http\Requests\SolicitarAnaliseRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function solicitar(SolicitarAnaliseRequest $request, BureauService $bureau, AnaliseCreditoService $avaliador)
    {
        $dados = $request->validated();

        $cliente = Cliente::firstOrCreate(
            ['cpf' => $dados['cpf']],
            [
                'nome' => $dados['nome'],
                'renda_mensal' => $dados['renda_mensal'],
                'email' => "cliente_{$dados['cpf']}@coop.local",
            ]
        );

        $analise = AnaliseCredito::create([
            'cliente_id' => $cliente->id,
            'cpf' => $dados['cpf'],
            'nome' => $dados['nome'],
            'renda_mensal' => $dados['renda_mensal'],
            'tipo_credito' => $dados['tipo_credito'],
            'valor_solicitado' => $dados['valor_solicitado'],
            'status' => StatusAnalise::PENDENTE,
        ]);

        if ((float) $dados['renda_mensal'] < AnaliseCreditoService::RENDA_MINIMA) {
            $analise->update([
                'status' => StatusAnalise::REPROVADO,
                'motivo_rejeicao' => 'Renda mínima insuficiente',
            ]);

            return response()->json($analise->fresh(), 201);
        }

        try {
            $score = $bureau->consultarScore($dados['cpf']);
        } catch (BureauIndisponivelException $e) {
            $analise->update([
                'status' => StatusAnalise::REPROVADO,
                'motivo_rejeicao' => 'Serviço de análise indisponível no momento. Tente novamente.',
            ]);

            return response()->json([
                'message' => 'Serviço de análise indisponível no momento. Tente novamente.',
                'analise' => $analise->fresh(),
            ], 503);
        }

        $resultado = $avaliador->avaliar(
            (float) $dados['renda_mensal'],
            $score,
            (float) $dados['valor_solicitado']
        );

        $analise->update([
            'score' => $score,
            'status' => $resultado['status'],
            'taxa_juros' => $resultado['taxa_juros'],
            'valor_parcela' => $resultado['valor_parcela'],
            'motivo_rejeicao' => $resultado['motivo_rejeicao'],
        ]);

        return response()->json($analise->fresh(), 201);
    }

    /**
     * Confirma a contratação de uma análise de crédito aprovada.
     *
     * POST /api/analise-credito/{id}/contratar
     *
     * Fluxo esperado:
     *  1. Buscar a análise pelo ID (retornar 404 se não encontrada).
     *  2. Verificar se o status é 'aprovado' (retornar 422 se não for).
     *  3. Atualizar o status para 'contratado'.
     *  4. Retornar confirmação de sucesso.
     *
     * ⭐ DIFERENCIAL OPCIONAL: Em vez de atualizar diretamente para 'contratado',
     *    atualize para 'processando_contratacao' e dispare o Job ProcessarContratacaoJob
     *    para a fila. O Job ficará responsável por finalizar e atualizar para 'contratado'.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function contratar($id)
    {
        $analise = AnaliseCredito::findOrFail($id);

        if ($analise->status !== StatusAnalise::APROVADO) {
            return response()->json([
                'message' => 'Somente análises aprovadas podem ser contratadas.',
                'analise' => $analise,
            ], 422);
        }

        $analise->update(['status' => StatusAnalise::CONTRATADO]);

        return response()->json([
            'message' => 'Crédito contratado com sucesso.',
            'analise' => $analise->fresh(),
        ]);
    }
}
