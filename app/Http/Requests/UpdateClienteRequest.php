<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $clienteId = $this->route('cliente');

        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'cpf' => ['sometimes', 'required', 'digits:11', 'unique:clientes,cpf,' . $clienteId],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:clientes,email,' . $clienteId],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'renda_mensal' => ['sometimes', 'required', 'numeric', 'min:0'],
        ];
    }
}
