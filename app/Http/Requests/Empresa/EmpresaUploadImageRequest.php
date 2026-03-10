<?php

namespace App\Http\Requests\Empresa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\VerificaEmpresa;

class EmpresaUploadImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $empresaId = $this->route('id');
        
        // Se não tem ID na rota, não pode autorizar
        if (!$empresaId) {
            return false;
        }
        
        // Verificar se a empresa pertence ao usuário autenticado
        return VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int)$empresaId);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tipo = $this->query('tipo');

        if ($tipo === 'banner') {
            return [
                'banner' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            ];
        } elseif ($tipo === 'logo') {
            return [
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            ];
        }

        return [
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
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
            'banner.required' => 'O banner é obrigatório.',
            'banner.image' => 'O banner deve ser uma imagem válida.',
            'banner.mimes' => 'O banner deve ser um arquivo do tipo: jpeg, png, jpg, gif, webp.',
            'banner.max' => 'O banner não pode ter mais que 5MB. Reduza o tamanho da imagem.',

            'logo.required' => 'A logo é obrigatória.',
            'logo.image' => 'A logo deve ser uma imagem válida.',
            'logo.mimes' => 'A logo deve ser um arquivo do tipo: jpeg, png, jpg, gif, webp.',
            'logo.max' => 'A logo não pode ter mais que 5MB. Reduza o tamanho da imagem.',
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
                'message' => 'Você não tem permissão para acessar esta empresa.'
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