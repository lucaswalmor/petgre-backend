<?php

namespace App\Http\Requests\Produto;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Produto;
use App\Helpers\VerificaEmpresa;

class ProdutoUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $produtoId = $this->route('id');

        // Se não tem ID na rota, não pode autorizar
        if (!$produtoId) {
            return false;
        }

        // Buscar o produto para verificar a empresa
        $produto = Produto::find($produtoId);

        if (!$produto) {
            return false;
        }

        // Verificar se o usuário tem acesso à empresa do produto
        return VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $produtoId = $this->route('id');

        return [
            // Relacionamentos opcionais
            'categoria_id' => 'sometimes|nullable|exists:categorias,id',
            'unidade_medida_id' => 'sometimes|nullable|exists:unidades_medidas,id',

            // Dados do produto (todos opcionais para update)
            'tipo' => 'sometimes|nullable|in:produto,servico',
            'nome' => 'sometimes|nullable|string|min:3|max:255',
            'imagem' => 'sometimes|nullable|string|max:500',
            'slug' => 'sometimes|nullable|string|max:255',
            'descricao' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
                function ($attribute, $value, $fail) {
                    // BUG-005 (Crítico): Validar caracteres potencialmente perigosos
                    if (!empty($value)) {
                        $caracteresPerigosos = ["'", '"', '--', ';', '<', '>'];
                        foreach ($caracteresPerigosos as $caractere) {
                            if (str_contains($value, $caractere)) {
                                $fail('A descrição contém caracteres não permitidos.');
                                return;
                            }
                        }
                    }
                },
            ],
            'preco' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'estoque' => 'sometimes|nullable|numeric|min:0|max:999999.999',
            'destaque' => 'sometimes|nullable|boolean',
            'ativo' => 'sometimes|nullable|boolean',

            // Novas colunas
            'marca' => 'sometimes|nullable|string|max:255',
            'sku' => 'sometimes|nullable|string|max:255|unique:produtos,sku,' . $produtoId,
            'preco_custo' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'estoque_minimo' => 'sometimes|nullable|numeric|min:0|max:999999.999',
            'ativar_estoque_minimo' => 'sometimes|nullable|boolean',
            'peso' => 'sometimes|nullable|numeric|min:0|max:999.999',
            'altura' => 'sometimes|nullable|numeric|min:0|max:9999.99',
            'largura' => 'sometimes|nullable|numeric|min:0|max:9999.99',
            'comprimento' => 'sometimes|nullable|numeric|min:0|max:9999.99',
            'ordem' => 'sometimes|nullable|integer|min:0|max:999999',
            'preco_promocional' => 'sometimes|nullable|numeric|min:0|max:999999.99|required_if:tem_promocao,true',
            'preco_promocional_percentual' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:1',
                'max:99',
                'required_if:tem_promocao,true',
                function ($attribute, $value, $fail) {
                    // BUG-N1: Só validar min/max se tem_promocao é true e há um valor
                    $temPromocao = request()->boolean('tem_promocao', false);
                    $hasValue = $value !== null && $value !== '' && $value !== false;
                    
                    if ($temPromocao && (!$hasValue || $value < 1 || $value > 99)) {
                        $fail('O percentual de desconto deve ser entre 1% e 99% quando há promoção ativa.');
                    }
                },
            ],
            'promocao_ate' => [
                'sometimes',
                'nullable',
                'date',
                'required_if:tem_promocao,true',
                function ($attribute, $value, $fail) {
                    // BUG-008: Validar data comparando apenas a data (sem componente de hora)
                    // Usar timezone America/Sao_Paulo para ambas as datas
                    try {
                        // Tenta criar a data a partir do formato Y-m-d
                        if (is_string($value)) {
                            $dataPromocao = \Carbon\Carbon::createFromFormat('Y-m-d', $value, 'America/Sao_Paulo');
                        } else {
                            // Se já for uma data, converte para o timezone
                            $dataPromocao = \Carbon\Carbon::parse($value)->timezone('America/Sao_Paulo');
                        }
                        $dataPromocao = $dataPromocao->startOfDay();
                        
                        // Hoje no timezone de São Paulo, também no início do dia
                        $hoje = \Carbon\Carbon::now('America/Sao_Paulo')->startOfDay();

                        if ($dataPromocao->lt($hoje)) {
                            $fail('A data de promoção deve ser hoje ou uma data futura.');
                        }
                    } catch (\Exception $e) {
                        $fail('A data de promoção é inválida.');
                    }
                },
            ],
            'tem_promocao' => 'sometimes|nullable|boolean',
            'vende_granel' => 'sometimes|nullable|boolean',
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
            // Relacionamentos
            'categoria_id.exists' => 'A categoria selecionada não existe.',
            'unidade_medida_id.exists' => 'A unidade de medida selecionada não existe.',

            // Dados do produto
            'tipo.in' => 'O tipo deve ser "produto" ou "servico".',

            'nome.string' => 'O nome deve ser um texto válido.',
            'nome.max' => 'O nome não pode ter mais que 255 caracteres.',

            'imagem.string' => 'A imagem deve ser um texto válido.',
            'imagem.max' => 'O caminho da imagem não pode ter mais que 500 caracteres.',

            'slug.string' => 'O slug deve ser um texto válido.',
            'slug.max' => 'O slug não pode ter mais que 255 caracteres.',

            'descricao.string' => 'A descrição deve ser um texto válido.',
            'descricao.max' => 'A descrição não pode ter mais que 1000 caracteres.',

            'preco.numeric' => 'O preço deve ser um valor numérico.',
            'preco.min' => 'O preço não pode ser negativo.',
            'preco.max' => 'O preço não pode ser maior que 999.999,99.',

            'estoque.numeric' => 'O estoque deve ser um valor numérico.',
            'estoque.min' => 'O estoque não pode ser negativo.',
            'estoque.max' => 'O estoque não pode ser maior que 999.999,999.',

            'destaque.boolean' => 'O campo destaque deve ser verdadeiro ou falso.',
            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',

            // Novas colunas
            'marca.string' => 'A marca deve ser um texto válido.',
            'marca.max' => 'A marca não pode ter mais que 255 caracteres.',

            'sku.string' => 'O SKU deve ser um texto válido.',
            'sku.max' => 'O SKU não pode ter mais que 255 caracteres.',
            'sku.unique' => 'Este SKU já está sendo usado por outro produto.',

            'preco_custo.numeric' => 'O preço de custo deve ser um valor numérico.',
            'preco_custo.min' => 'O preço de custo não pode ser negativo.',
            'preco_custo.max' => 'O preço de custo não pode ser maior que 999.999,99.',

            'estoque_minimo.numeric' => 'O estoque mínimo deve ser um valor numérico.',
            'estoque_minimo.min' => 'O estoque mínimo não pode ser negativo.',
            'estoque_minimo.max' => 'O estoque mínimo não pode ser maior que 999.999,999.',

            'peso.numeric' => 'O peso deve ser um valor numérico.',
            'peso.min' => 'O peso não pode ser negativo.',
            'peso.max' => 'O peso não pode ser maior que 999,999 kg.',

            'altura.numeric' => 'A altura deve ser um valor numérico.',
            'altura.min' => 'A altura não pode ser negativa.',
            'altura.max' => 'A altura não pode ser maior que 9.999,99 cm.',

            'largura.numeric' => 'A largura deve ser um valor numérico.',
            'largura.min' => 'A largura não pode ser negativa.',
            'largura.max' => 'A largura não pode ser maior que 9.999,99 cm.',

            'comprimento.numeric' => 'O comprimento deve ser um valor numérico.',
            'comprimento.min' => 'O comprimento não pode ser negativo.',
            'comprimento.max' => 'O comprimento não pode ser maior que 9.999,99 cm.',

            'ordem.integer' => 'A ordem deve ser um número inteiro.',
            'ordem.min' => 'A ordem não pode ser negativa.',
            'ordem.max' => 'A ordem não pode ser maior que 999.999.',

            'preco_promocional.numeric' => 'O preço promocional deve ser um valor numérico.',
            'preco_promocional.min' => 'O preço promocional não pode ser negativo.',
            'preco_promocional.max' => 'O preço promocional não pode ser maior que 999.999,99.',

            'preco_promocional_percentual.numeric' => 'O percentual de desconto deve ser um valor numérico.',
            'preco_promocional_percentual.min' => 'O percentual de desconto deve ser no mínimo 1%.',
            'preco_promocional_percentual.max' => 'O percentual de desconto deve ser no máximo 99%.',
            'preco_promocional_percentual.required_if' => 'O percentual de desconto é obrigatório quando há promoção.',

            'promocao_ate.date' => 'A data de promoção deve ser uma data válida.',

            'tem_promocao.boolean' => 'O campo promoção deve ser verdadeiro ou falso.',
            'vende_granel.boolean' => 'O campo vende a granel deve ser verdadeiro ou falso.',
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
            // Verificar se já existe um produto com o mesmo nome nesta empresa (exceto o atual)
            $produtoId = $this->route('id');
            $nome = $this->input('nome');

            if ($nome && $produtoId) {
                $produto = Produto::find($produtoId);

                if ($produto) {
                    $existe = Produto::where('empresa_id', $produto->empresa_id)
                        ->where('nome', $nome)
                        ->where('id', '!=', $produtoId)
                        ->exists();

                    if ($existe) {
                        $validator->errors()->add('nome', 'Já existe um produto com este nome nesta empresa.');
                    }
                }
            }

            // Se forneceu nome mas não forneceu slug, gerar automaticamente
            if ($this->has('nome') && (!$this->has('slug') || empty($this->slug))) {
                $this->merge(['slug' => \Illuminate\Support\Str::slug($this->nome)]);
            }
        });
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para editar este produto.'
            ], 403)
        );
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
            'message' => 'Dados inválidos. Verifique os erros abaixo.',
            'errors' => $validator->errors()
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}