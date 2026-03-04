<?php

namespace App\Http\Requests\Kit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Helpers\VerificaEmpresa;
use App\Models\Kit;

class KitUploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kitId = $this->route('id');
        if (!$kitId) {
            return false;
        }
        $kit = Kit::find($kitId);
        if (!$kit) {
            return false;
        }
        return VerificaEmpresa::verificaEmpresaPertenceAoUsuario($kit->empresa_id);
    }

    public function rules(): array
    {
        return [
            'imagem' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:15360',
        ];
    }

    public function messages(): array
    {
        return [
            'imagem.required' => 'A imagem é obrigatória.',
            'imagem.image' => 'O arquivo deve ser uma imagem válida.',
            'imagem.mimes' => 'A imagem deve ser jpeg, png, jpg, gif ou webp.',
            'imagem.max' => 'A imagem não pode ter mais que 15MB.',
        ];
    }

    protected function failedAuthorization()
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para acessar este kit.',
            ], 403)
        );
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException($validator, response()->json([
            'success' => false,
            'message' => 'Dados inválidos para upload de imagem.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
