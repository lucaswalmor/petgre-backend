<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\VerificaEmpresa;

class UsuarioStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Verificar se há token de autenticação (bearer token)
        $hasToken = $this->bearerToken() !== null;

        if ($hasToken) {
            // Verificar se a empresa pertence ao usuário autenticado
            return VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int)$this->empresa_id);
        }

        // Se não há token, é cliente e pode se cadastrar (rota pública)
        return true;
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hasToken = $this->bearerToken() !== null;
        $isFuncionario = $this->has('permissoes') && $this->has('empresa_id') && $this->empresa_id;

        $rules = [
            // Campos principais do usuário
            'nome' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Verificar se já existe usuário ATIVO com este email e tipo_cadastro = 1 (Cliente)
                    $usuarioExistente = \App\Models\User::where('email', $value)
                        ->where('tipo_cadastro', 1)
                        ->first();

                    if ($usuarioExistente) {
                        if ($usuarioExistente->ativo) {
                            $fail('Este email já está sendo usado por outro usuário.');
                        } else {
                            // Usuário existe mas está inativo - retornar flag para reativação
                            $fail('conta_inativa_por_email')->translate('conta_inativa_por_email', ['email' => $value]);
                        }
                    }
                },
            ],
            // Senha obrigatória apenas para clientes, funcionários recebem senha automática
            'password' => $isFuncionario ? 'nullable|string|min:8' : 'required|string|min:8',
            'telefone' => [
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) use ($isFuncionario) {
                    // Verificar se já existe CLIENTE (tipo_cadastro = 1) com este telefone
                    // Lojistas podem ter telefones iguais, mas clientes não podem repetir
                    $usuarioExistente = \App\Models\User::where('telefone', $value)
                        ->where('tipo_cadastro', 1)
                        ->first();

                    if ($usuarioExistente) {
                        if ($usuarioExistente->ativo) {
                            $fail('Este telefone já está sendo usado por outro cliente.');
                        } else {
                            // Usuário existe mas está inativo
                            $fail('conta_inativa_por_telefone');
                        }
                    }
                },
            ],
            'permissoes' => 'sometimes|nullable|array',
            'permissoes.*' => 'exists:permissoes,id',
            'empresa_id' => 'sometimes|nullable|exists:empresas,id',

            // Endereço (obrigatório apenas para clientes, opcional para funcionários)
            'endereco' => 'nullable|array',
        ];

        // Validações de endereço condicionais
        if (!$isFuncionario) {
            // Para clientes: endereço é obrigatório
            $rules['endereco.cep'] = 'required|string|max:10';
            $rules['endereco.rua'] = 'required|string|max:255';
            $rules['endereco.numero'] = 'required|string|max:20';
            $rules['endereco.bairro'] = 'required|string|max:255';
            $rules['endereco.cidade'] = 'required|string|max:255';
            $rules['endereco.estado'] = 'required|string|size:2';
        } else {
            // Para funcionários: endereço é opcional (usarão endereço da empresa)
            $rules['endereco.cep'] = 'sometimes|nullable|string|max:10';
            $rules['endereco.rua'] = 'sometimes|nullable|string|max:255';
            $rules['endereco.numero'] = 'sometimes|nullable|string|max:20';
            $rules['endereco.bairro'] = 'sometimes|nullable|string|max:255';
            $rules['endereco.cidade'] = 'sometimes|nullable|string|max:255';
            $rules['endereco.estado'] = 'sometimes|nullable|string|size:2';
        }

        // Campos sempre opcionais no endereço
        $rules['endereco.complemento'] = 'nullable|string|max:255';
        $rules['endereco.ponto_referencia'] = 'nullable|string|max:255';
        $rules['endereco.observacoes'] = 'nullable|string|max:500';

        if ($hasToken) {
            // Se há token, é funcionário: empresa_id e permissoes são obrigatórios
            $rules['empresa_id'] = 'required|exists:empresas,id';
            $rules['permissoes'] = 'required|array|min:1';
        }

        return $rules;
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

            'password.required' => 'A senha é obrigatória.',
            'password.string' => 'A senha deve ser um texto válido.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',

            'telefone.required' => 'O telefone é obrigatório.',
            'telefone.string' => 'O telefone deve ser um texto válido.',
            'telefone.max' => 'O telefone não pode ter mais que 20 caracteres.',

            'permissoes.array' => 'As permissões devem ser enviadas como um array.',
            'permissoes.*.exists' => 'Uma ou mais permissões selecionadas não existem.',

            'empresa_id.exists' => 'A empresa selecionada não existe.',

            // Endereço
            'endereco.array' => 'Os dados de endereço devem ser um objeto válido.',

            'endereco.cep.required' => 'O CEP é obrigatório.',
            'endereco.cep.string' => 'O CEP deve ser um texto válido.',
            'endereco.cep.max' => 'O CEP não pode ter mais que 10 caracteres.',

            'endereco.rua.required' => 'A rua é obrigatória.',
            'endereco.rua.string' => 'A rua deve ser um texto válido.',
            'endereco.rua.max' => 'A rua não pode ter mais que 255 caracteres.',

            'endereco.numero.required' => 'O número é obrigatório.',
            'endereco.numero.string' => 'O número deve ser um texto válido.',
            'endereco.numero.max' => 'O número não pode ter mais que 20 caracteres.',

            'endereco.complemento.string' => 'O complemento deve ser um texto válido.',
            'endereco.complemento.max' => 'O complemento não pode ter mais que 255 caracteres.',

            'endereco.bairro.required' => 'O bairro é obrigatório.',
            'endereco.bairro.string' => 'O bairro deve ser um texto válido.',
            'endereco.bairro.max' => 'O bairro não pode ter mais que 255 caracteres.',

            'endereco.cidade.required' => 'A cidade é obrigatória.',
            'endereco.cidade.string' => 'A cidade deve ser um texto válido.',
            'endereco.cidade.max' => 'A cidade não pode ter mais que 255 caracteres.',

            'endereco.estado.required' => 'O estado é obrigatório.',
            'endereco.estado.string' => 'O estado deve ser um texto válido.',
            'endereco.estado.size' => 'O estado deve ter exatamente 2 caracteres (sigla).',

            'endereco.ponto_referencia.string' => 'O ponto de referência deve ser um texto válido.',
            'endereco.ponto_referencia.max' => 'O ponto de referência não pode ter mais que 255 caracteres.',

            'endereco.observacoes.string' => 'As observações devem ser um texto válido.',
            'endereco.observacoes.max' => 'As observações não podem ter mais que 500 caracteres.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Se for FormData, precisamos converter arrays aninhados
        if ($this->isMethod('post') && $this->header('Content-Type') !== 'application/json') {
            $data = $this->all();

            // Converter arrays de formulário para arrays PHP
            if (isset($data['endereco']) && is_array($data['endereco'])) {
                $this->merge(['endereco' => $data['endereco']]);
            }
        }
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        // Validações adicionais podem ser implementadas aqui se necessário
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
                'message' => 'Você não tem permissão para criar funcionários nesta empresa.'
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