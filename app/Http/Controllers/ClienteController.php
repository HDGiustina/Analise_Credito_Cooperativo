<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;

class ClienteController extends Controller
{
    /**
     * Lista todos os clientes cadastrados.
     *
     * GET /api/clientes
     *
     * @return JsonResponse
     */
    public function index()
    {
        return response()->json(
            Cliente::orderBy('id', 'desc')->paginate(15)
        );
    }

    /**
     * Cadastra um novo cliente.
     *
     * POST /api/clientes
     *
     * Validações esperadas:
     *  - nome: obrigatório, string
     *  - cpf: obrigatório, 11 dígitos numéricos, único na tabela clientes
     *  - email: obrigatório, formato e-mail válido, único na tabela clientes
     *  - telefone: opcional, string
     *  - renda_mensal: obrigatório, numérico, mínimo de 0
     *
     * @return JsonResponse
     */
    public function store(StoreClienteRequest $request)
    {
        $cliente = Cliente::create($request->validated());

        return response()->json($cliente, 201);
    }

    /**
     * Exibe os dados de um cliente específico.
     *
     * GET /api/clientes/{id}
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show($id)
    {
        return response()->json(Cliente::findOrFail($id));
    }

    /**
     * Atualiza os dados de um cliente existente.
     *
     * PUT /api/clientes/{id}
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(UpdateClienteRequest $request, $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->update($request->validated());

        return response()->json($cliente);
    }

    /**
     * Remove um cliente do sistema.
     *
     * DELETE /api/clientes/{id}
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        Cliente::findOrFail($id)->delete();

        return response()->noContent();
    }
}
