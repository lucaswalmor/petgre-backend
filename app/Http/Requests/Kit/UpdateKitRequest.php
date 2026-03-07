<?php

namespace App\Http\Requests\Kit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UpdateKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'preco' => 'required|numeric|min:0',
            'ativo' => 'boolean',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.quantidade' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do kit é obrigatório.',
            'nome.max' => 'O nome não pode ter mais que 255 caracteres.',
            'preco.required' => 'O preço é obrigatório.',
            'preco.numeric' => 'O preço deve ser um número.',
            'preco.min' => 'O preço não pode ser negativo.',
            'itens.required' => 'Adicione pelo menos um produto ao kit.',
            'itens.min' => 'O kit deve ter pelo menos um produto.',
            'itens.*.produto_id.required' => 'O produto é obrigatório.',
            'itens.*.produto_id.exists' => 'O produto selecionado não existe.',
            'itens.*.quantidade.required' => 'A quantidade é obrigatória.',
            'itens.*.quantidade.integer' => 'A quantidade deve ser um número inteiro.',
            'itens.*.quantidade.min' => 'A quantidade deve ser pelo menos 1.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $kit = \App\Models\Kit::find($this->route('id'));
            $empresaId = $kit ? (int) $kit->empresa_id : (int) $this->input('empresa_id');
            if ($empresaId && $this->has('itens') && is_array($this->itens)) {
                foreach ($this->itens as $index => $item) {
                    $produto = \App\Models\Produto::find($item['produto_id'] ?? 0);
                    if ($produto && $produto->empresa_id !== $empresaId) {
                        $validator->errors()->add("itens.{$index}.produto_id", 'O produto não pertence à empresa.');
                    }
                }
            }
        });
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new ValidationException($validator, response()->json([
            'success' => false,
            'message' => 'Verifique os dados do kit e tente novamente.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
