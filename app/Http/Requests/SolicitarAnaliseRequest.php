<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitarAnaliseRequest extends FormRequest
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
        return [
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'digits:11'],
            'renda_mensal' => ['required', 'numeric', 'min:0'],
            'tipo_credito' => ['required', Rule::in(['pessoal', 'imobiliario', 'automotivo'])],
            'valor_solicitado' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
