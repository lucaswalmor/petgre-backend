<?php

namespace App\Http\Requests\FichaPet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FichaPetStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|exists:empresas,id',
            'nome' => 'required|string|min:2|max:100',
            'raca' => 'required|string|min:2|max:100',
            'porte' => 'required|in:pequeno,medio,grande',
            'tamanho_pelagem' => 'required|in:curta,media,longa',
            'idade' => 'nullable|integer|min:0|max:30',
            'unidade_idade' => 'nullable|in:meses,anos',
            'comportamento' => 'nullable|string|max:500',
            'alergias' => 'nullable|string|max:500',
            'foto_url' => 'nullable|string|max:255'
        ];
    }

    public function messages(): array
    {
        return [
            'empresa_id.required' => 'A empresa é obrigatória.',
            'empresa_id.exists' => 'A empresa não existe.',
            'nome.required' => 'O nome do pet é obrigatório.',
            'nome.min' => 'O nome deve ter pelo menos 2 caracteres.',
            'raca.required' => 'A raça é obrigatória.',
            'porte.required' => 'O porte é obrigatório.',
            'porte.in' => 'O porte deve ser pequeno, médio ou grande.',
            'tamanho_pelagem.required' => 'O tamanho da pelagem é obrigatório.',
            'tamanho_pelagem.in' => 'O tamanho da pelagem deve ser curta, média ou longa.'
        ];
    }
}
