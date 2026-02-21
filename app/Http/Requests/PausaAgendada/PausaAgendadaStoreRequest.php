<?php

namespace App\Http\Requests\PausaAgendada;

use App\Helpers\VerificaEmpresa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PausaAgendadaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresasIds = VerificaEmpresa::obterEmpresasDoUsuario()->pluck('id')->toArray();

        return [
            'empresa_id' => ['required', 'integer', Rule::in($empresasIds)],
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after:data_inicio',
            'motivo' => 'nullable|string|max:255',
            'recorrente' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'empresa_id.required' => 'A empresa é obrigatória.',
            'empresa_id.in' => 'Empresa não autorizada.',
            'data_inicio.required' => 'A data/hora de início é obrigatória.',
            'data_inicio.date' => 'A data/hora de início deve ser válida.',
            'data_fim.required' => 'A data/hora de fim é obrigatória.',
            'data_fim.date' => 'A data/hora de fim deve ser válida.',
            'data_fim.after' => 'A data/hora de fim deve ser posterior ao início.',
            'motivo.max' => 'O motivo não pode ter mais que 255 caracteres.',
            'recorrente.boolean' => 'O campo recorrente deve ser verdadeiro ou falso.',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new ValidationException($validator, response()->json([
            'success' => false,
            'message' => 'Dados inválidos para cadastro da pausa.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
