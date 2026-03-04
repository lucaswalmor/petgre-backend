<?php

namespace App\Http\Requests\Produto;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\VerificaEmpresa;

class ProdutoLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Verifica se o usuário possui acesso à empresa de cada item
        $produtos = $this->input('produtos', []);
        foreach ($produtos as $produto) {
            if (empty($produto['empresa_id']) || !VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int)$produto['empresa_id'])) {
                return false;
            }
        }
        return true;
    }

    public function rules(): array
    {
        return [
            'produtos' => 'required|array|min:1',
            'produtos.*.empresa_id' => 'required|exists:empresas,id',
            'produtos.*.categoria_id' => 'required|exists:categorias,id',
            'produtos.*.unidade_medida_id' => 'required|exists:unidades_medidas,id',
            'produtos.*.tipo' => 'nullable|in:produto,servico',
            'produtos.*.nome' => 'required|string|min:3|max:255',
            'produtos.*.descricao' => 'nullable|string|max:1000',
            'produtos.*.preco' => 'required|numeric|min:0.01|max:999999.99',
            'produtos.*.estoque' => 'nullable|numeric|min:0|max:999999.999',
            'produtos.*.destaque' => 'nullable|boolean',
            'produtos.*.ativo' => 'nullable|boolean',
            'produtos.*.marca' => 'nullable|string|max:255',
            'produtos.*.sku' => 'nullable|string|max:255',
            'produtos.*.preco_custo' => 'nullable|numeric|min:0|max:999999.99',
            'produtos.*.estoque_minimo' => 'nullable|numeric|min:0|max:999999.999',
            'produtos.*.ativar_estoque_minimo' => 'nullable|boolean',
            'produtos.*.peso' => 'nullable|numeric|min:0|max:999.999',
            'produtos.*.altura' => 'nullable|numeric|min:0|max:9999.99',
            'produtos.*.largura' => 'nullable|numeric|min:0|max:9999.99',
            'produtos.*.comprimento' => 'nullable|numeric|min:0|max:9999.99',
            'produtos.*.ordem' => 'nullable|integer|min:0|max:999999',
            'produtos.*.preco_promocional' => 'nullable|numeric|min:0|max:999999.99',
            'produtos.*.promocao_ate' => 'nullable|date|after:today',
            'produtos.*.tem_promocao' => 'nullable|boolean',
            'produtos.*.vende_granel' => 'nullable|boolean',
        ];
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para cadastrar produtos nesta empresa.'
            ], 403)
        );
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'Dados inválidos para cadastro em lote.',
            'errors' => $validator->errors()
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}
