<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\User;
use App\Helpers\VerificaEmpresa;

class UsuarioUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $usuarioId = $this->route('id');
        
        // Se não tem ID na rota, não pode autorizar
        if (!$usuarioId) {
            return false;
        }
        
        // Verificar se o usuário autenticado e o usuário sendo editado pertencem à mesma empresa
        return VerificaEmpresa::verificaUsuariosMesmaEmpresa((int)$usuarioId);
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Para edição, sempre permitir permissoes
        // A validação de quem pode alterar permissões fica no controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Campos principais do usuário (todos opcionais para update)
            'nome' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    $usuarioId = $this->route('id');
                    $usuario = User::find($usuarioId);

                    if ($usuario) {
                        // Verificar se já existe outro usuário com este email e mesmo tipo_cadastro
                        $exists = User::where('email', $value)
                            ->where('tipo_cadastro', $usuario->tipo_cadastro)
                            ->where('id', '!=', $usuarioId)
                            ->exists();

                        if ($exists) {
                            $fail('Este email já está sendo usado por outro usuário.');
                        }
                    }
                },
            ],
            'password' => 'sometimes|nullable|string|min:8',
            'telefone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) {
                    $usuarioId = $this->route('id');
                    $usuario = User::find($usuarioId);

                    // Verificar se já existe outro CLIENTE (tipo_cadastro = 1) com este telefone
                    // Lojistas podem ter telefones iguais, mas clientes não podem repetir
                    $query = \App\Models\User::where('telefone', $value)
                        ->where('tipo_cadastro', 1)
                        ->where('id', '!=', $usuarioId);

                    // Se o usuário atual for lojista (tipo_cadastro != 1), verifica se existe cliente com esse telefone
                    // Se o usuário atual for cliente, verifica se existe outro cliente com esse telefone
                    $exists = $query->exists();

                    if ($exists) {
                        $fail('Este telefone já está sendo usado por outro cliente.');
                    }
                },
            ],
            'permissoes' => 'sometimes|nullable|array',
            'permissoes.*' => 'exists:permissoes,id',
            'ativo' => 'sometimes|boolean',
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
            // Campos principais do usuário
            'nome.required' => 'O nome do usuário é obrigatório.',
            'nome.string' => 'O nome deve ser um texto válido.',
            'nome.max' => 'O nome não pode ter mais que 255 caracteres.',

            'email.required' => 'O email é obrigatório.',
            'email.string' => 'O email deve ser um texto válido.',
            'email.email' => 'O email deve ter um formato válido.',
            'email.max' => 'O email não pode ter mais que 255 caracteres.',
            'email.unique' => 'Este email já está sendo usado por outro usuário.',

            'password.string' => 'A senha deve ser um texto válido.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',

            'telefone.required' => 'O telefone é obrigatório.',
            'telefone.string' => 'O telefone deve ser um texto válido.',
            'telefone.max' => 'O telefone não pode ter mais que 20 caracteres.',

            'permissoes.array' => 'As permissões devem ser enviadas como um array.',
            'permissoes.*.exists' => 'Uma ou mais permissões selecionadas não existem.',

            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',
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
            // Verificar se está tentando alterar permissões de usuário master
            if ($this->has('permissoes')) {
                $usuario = User::find($this->route('id'));

                if ($usuario && $usuario->isMaster()) {
                    $validator->errors()->add('permissoes', 'Não é possível alterar as permissões de um usuário master.');
                }
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
                'message' => 'Você não tem permissão para editar este usuário.'
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
