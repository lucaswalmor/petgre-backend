<?php

namespace App\Http\Requests\Produto;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\VerificaEmpresa;
use App\Models\Produto;

class ProdutoUploadImageRequest extends FormRequest
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
        return [
            'imagem' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
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
            'imagem.required' => 'A imagem é obrigatória.',
            'imagem.image' => 'O arquivo deve ser uma imagem válida.',
            'imagem.mimes' => 'A imagem deve ser um arquivo do tipo: jpeg, png, jpg, gif, webp.',
            'imagem.max' => 'A imagem não pode ter mais que 5MB. Reduza o tamanho da imagem.',
        ];
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
                'message' => 'Você não tem permissão para acessar este produto.'
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
            'message' => 'Dados inválidos para upload de imagem.',
            'errors' => $validator->errors()
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}