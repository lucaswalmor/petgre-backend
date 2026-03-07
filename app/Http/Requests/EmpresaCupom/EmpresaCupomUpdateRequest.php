<?php

namespace App\Http\Requests\EmpresaCupom;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;

class EmpresaCupomUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $cupomId = $this->route('id');

        return [
            'codigo' => 'sometimes|required|string|max:50|unique:empresa_cupons,codigo,' . $cupomId,
            'tipo' => 'sometimes|required|in:percentual,fixed',
            'valor' => 'sometimes|required|numeric|min:0.01',
            'valor_minimo' => 'nullable|numeric|min:0',
            'data_inicio' => 'sometimes|required|date',
            'data_fim' => 'sometimes|required|date|after:data_inicio',
            'limite_uso' => 'nullable|integer|min:1',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'O código do cupom é obrigatório.',
            'codigo.unique' => 'Este código de cupom já está em uso.',
            'codigo.max' => 'O código do cupom não pode ter mais que 50 caracteres.',

            'tipo.required' => 'O tipo do cupom é obrigatório.',
            'tipo.in' => 'O tipo deve ser percentual ou valor fixo.',

            'valor.required' => 'O valor do desconto é obrigatório.',
            'valor.numeric' => 'O valor deve ser um número.',
            'valor.min' => 'O valor deve ser maior que zero.',

            'valor_minimo.numeric' => 'O valor mínimo deve ser um número.',
            'valor_minimo.min' => 'O valor mínimo não pode ser negativo.',

            'data_inicio.required' => 'A data de início é obrigatória.',
            'data_inicio.date' => 'A data de início deve ser uma data válida.',

            'data_fim.required' => 'A data de fim é obrigatória.',
            'data_fim.date' => 'A data de fim deve ser uma data válida.',
            'data_fim.after' => 'A data de fim deve ser posterior à data de início.',

            'limite_uso.integer' => 'O limite de uso deve ser um número inteiro.',
            'limite_uso.min' => 'O limite de uso deve ser pelo menos 1.',

            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validações adicionais para percentual
            if ($this->tipo === 'percentual' && $this->valor > 100) {
                $validator->errors()->add('valor', 'O percentual não pode ser maior que 100%.');
            }

            // Validar código único globalmente (exceto o próprio cupom)
            if ($this->has('codigo')) {
                $cupomId = $this->route('id');
                $existingCupom = \App\Models\EmpresaCupom::where('codigo', $this->codigo)
                    ->where('id', '!=', $cupomId)
                    ->first();

                if ($existingCupom) {
                    $validator->errors()->add('codigo', 'Este código de cupom já está em uso. Escolha outro código.');
                }
            }
        });
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'Dados inválidos para atualização do cupom.',
            'errors' => $validator->errors()
        ], 422);

        throw new ValidationException($validator, $response);
    }
}