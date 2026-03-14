<?php

namespace App\Http\Requests\FichaPet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FichaPetUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'nome' => 'sometimes|string|min:2|max:100',
            'raca' => 'sometimes|string|min:2|max:100',
            'porte' => 'sometimes|in:pequeno,medio,grande',
            'tamanho_pelagem' => 'sometimes|in:curta,media,longa',
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
            'nome.min' => 'O nome deve ter pelo menos 2 caracteres.',
            'porte.in' => 'O porte deve ser pequeno, médio ou grande.',
            'tamanho_pelagem.in' => 'O tamanho da pelagem deve ser curta, média ou longa.'
        ];
    }
}
