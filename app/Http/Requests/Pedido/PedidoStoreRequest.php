<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PedidoStoreRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Dados do pedido
            'empresa_id' => 'required|exists:empresas,id',
            'pagamento_id' => 'required|exists:formas_pagamentos,id',
            'subtotal' => 'required|numeric|min:0',
            'desconto' => 'nullable|numeric|min:0',
            'frete' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'observacoes' => 'nullable|string|max:1000',
            'foi_entrega' => 'nullable|boolean',

            // Cupons
            'cupom_tipo' => 'nullable|in:sistema,empresa',
            'cupom_id' => 'nullable|integer',
            'cupom_valor' => 'nullable|numeric|min:0',

            // Itens do pedido (produto_id ou kit_id por item)
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'nullable|exists:produtos,id',
            'itens.*.kit_id' => 'nullable|exists:kits,id',
            'itens.*.quantidade' => 'required|numeric|min:0.1',
            'itens.*.preco_unitario' => 'required|numeric|min:0',
            'itens.*.subtotal' => 'required|numeric|min:0',
            'itens.*.observacoes' => 'nullable|string|max:255',

            // Endereço do pedido (obrigatório apenas se foi_entrega = true)
            'endereco' => 'nullable|array',
            'endereco.endereco_id' => 'nullable|exists:usuarios_enderecos,id',
            'endereco.observacoes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Dados do pedido
            'empresa_id.required' => 'A empresa é obrigatória.',
            'empresa_id.exists' => 'A empresa selecionada não existe.',

            'pagamento_id.required' => 'A forma de pagamento é obrigatória.',
            'pagamento_id.exists' => 'A forma de pagamento selecionada não existe.',

            'subtotal.required' => 'O subtotal é obrigatório.',
            'subtotal.numeric' => 'O subtotal deve ser um valor numérico.',
            'subtotal.min' => 'O subtotal não pode ser negativo.',

            'desconto.numeric' => 'O desconto deve ser um valor numérico.',
            'desconto.min' => 'O desconto não pode ser negativo.',

            'frete.numeric' => 'O frete deve ser um valor numérico.',
            'frete.min' => 'O frete não pode ser negativo.',

            'total.required' => 'O total é obrigatório.',
            'total.numeric' => 'O total deve ser um valor numérico.',
            'total.min' => 'O total não pode ser negativo.',

            'observacoes.string' => 'As observações devem ser um texto válido.',
            'observacoes.max' => 'As observações não podem ter mais que 1000 caracteres.',

            'foi_entrega.boolean' => 'O campo foi_entrega deve ser verdadeiro ou falso.',

            'cupom_tipo.in' => 'O tipo de cupom deve ser sistema ou empresa.',
            'cupom_id.integer' => 'O ID do cupom deve ser um número inteiro.',
            'cupom_valor.numeric' => 'O valor do cupom deve ser um número.',
            'cupom_valor.min' => 'O valor do cupom não pode ser negativo.',

            // Itens do pedido
            'itens.required' => 'Os itens do pedido são obrigatórios.',
            'itens.array' => 'Os itens devem ser enviados como um array.',
            'itens.min' => 'O pedido deve ter pelo menos 1 item.',

            'itens.*.produto_id.exists' => 'O produto selecionado não existe.',
            'itens.*.kit_id.exists' => 'O kit selecionado não existe.',

            'itens.*.quantidade.required' => 'A quantidade é obrigatória.',
            'itens.*.quantidade.min' => 'A quantidade deve ser pelo menos 0,1.',

            'itens.*.preco_unitario.required' => 'O preço unitário é obrigatório.',
            'itens.*.preco_unitario.numeric' => 'O preço unitário deve ser um valor numérico.',
            'itens.*.preco_unitario.min' => 'O preço unitário não pode ser negativo.',

            'itens.*.subtotal.required' => 'O subtotal do item é obrigatório.',
            'itens.*.subtotal.numeric' => 'O subtotal do item deve ser um valor numérico.',
            'itens.*.subtotal.min' => 'O subtotal do item não pode ser negativo.',

            'itens.*.observacoes.string' => 'As observações do item devem ser um texto válido.',
            'itens.*.observacoes.max' => 'As observações do item não podem ter mais que 255 caracteres.',

            // Endereço
            'endereco.array' => 'Os dados de endereço devem ser um objeto válido.',

            'endereco.endereco_id.exists' => 'O endereço selecionado não existe.',

            'endereco.observacoes.max' => 'As observações do endereço não podem ter mais que 500 caracteres.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Verificar se o endereço é obrigatório apenas para entrega
            $foiEntrega = $this->boolean('foi_entrega');

            if ($foiEntrega && !$this->has('endereco.endereco_id')) {
                $validator->errors()->add('endereco', 'O endereço de entrega é obrigatório para pedidos de entrega.');
            }

            // Verificar se os endereços pertencem ao usuário (apenas se foi enviado)
            if ($this->has('endereco.endereco_id')) {
                $enderecoUsuario = \App\Models\UsuarioEnderecos::where('id', $this->input('endereco.endereco_id'))
                    ->where('usuario_id', Auth::id())
                    ->exists();

                if (!$enderecoUsuario) {
                    $validator->errors()->add('endereco.endereco_id', 'Este endereço não pertence ao usuário.');
                }
            }

            // Cada item deve ter produto_id ou kit_id (não ambos)
            if ($this->has('itens') && is_array($this->itens)) {
                foreach ($this->itens as $index => $item) {
                    $hasProduto = !empty($item['produto_id'] ?? null);
                    $hasKit = !empty($item['kit_id'] ?? null);
                    if (!$hasProduto && !$hasKit) {
                        $validator->errors()->add("itens.{$index}.produto_id", 'Cada item deve ter produto_id ou kit_id.');
                        continue;
                    }
                    if ($hasProduto && $hasKit) {
                        $validator->errors()->add("itens.{$index}.produto_id", 'Cada item deve ter apenas produto_id ou kit_id.');
                        continue;
                    }

                    if ($hasKit) {
                        $kit = \App\Models\Kit::where('id', $item['kit_id'])
                            ->where('empresa_id', $this->empresa_id)
                            ->where('ativo', true)
                            ->first();
                        if (!$kit) {
                            $validator->errors()->add("itens.{$index}.kit_id", 'Kit não encontrado, inativo ou não pertence à empresa.');
                        }
                        continue;
                    }

                    $produto = \App\Models\Produto::where('id', $item['produto_id'])
                        ->where('ativo', true)
                        ->first();

                    if (!$produto) {
                        $validator->errors()->add("itens.{$index}.produto_id", 'Produto não encontrado ou inativo.');
                        continue;
                    }

                    if ($produto->empresa_id !== (int)$this->empresa_id) {
                        $validator->errors()->add("itens.{$index}.produto_id", 'Este produto não pertence à empresa selecionada.');
                    }

                    // Validar estoque apenas para produtos (não para serviços)
                    if ($produto->tipo !== 'servico') {
                        $quantidadeParaValidar = $produto->vende_granel ? $item['quantidade'] / 1000 : $item['quantidade'];
                        if (isset($produto->estoque) && $produto->estoque < $quantidadeParaValidar) {
                            $validator->errors()->add("itens.{$index}.quantidade", "Estoque insuficiente. Disponível: {$produto->estoque}");
                        }
                    }
                }
            }
        });
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'Dados inválidos para criação do pedido.',
            'errors' => $validator->errors()
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}