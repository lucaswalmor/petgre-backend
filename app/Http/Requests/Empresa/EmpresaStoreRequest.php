<?php

namespace App\Http\Requests\Empresa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class EmpresaStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Se for FormData, precisamos converter arrays aninhados
        if ($this->isMethod('post') && !$this->hasHeader('Content-Type', 'application/json')) {
            $data = $this->all();

            // Converter arrays de formulário para arrays PHP
            if (isset($data['endereco']) && is_array($data['endereco'])) {
                $this->merge(['endereco' => $data['endereco']]);
            }

            if (isset($data['usuario_admin']) && is_array($data['usuario_admin'])) {
                $this->merge(['usuario_admin' => $data['usuario_admin']]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_filial' => 'nullable|boolean',
            // Campos principais da empresa
            'tipo_pessoa' => 'required|boolean|in:0,1',
            'razao_social' => 'required|string|max:255|unique:empresas,razao_social',
            'nome_fantasia' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255|unique:empresas,slug',
            'email' => 'required|email|max:255',
            'telefone' => 'required|string|max:20',
            'cpf_cnpj' => [
                'required',
                'string',
                'unique:empresas,cpf_cnpj',
                function ($attribute, $value, $fail) {
                    // Remove caracteres não numéricos
                    $numero = preg_replace('/[^0-9]/', '', $value);

                    // Verifica se é pessoa jurídica (0) ou física (1)
                    $tipoPessoa = $this->input('tipo_pessoa', 0);

                    if ($tipoPessoa === 0) {
                        // Pessoa Jurídica - CNPJ (14 dígitos)
                        if (strlen($numero) !== 14) {
                            $fail('O CNPJ deve ter 14 dígitos.');
                            return;
                        }

                        // Formato CNPJ: XX.XXX.XXX/XXXX-XX
                        if (!preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/', $value)) {
                            $fail('O CNPJ deve ter o formato XX.XXX.XXX/XXXX-XX.');
                            return;
                        }
                    } else {
                        // Pessoa Física - CPF (11 dígitos)
                        if (strlen($numero) !== 11) {
                            $fail('O CPF deve ter 11 dígitos.');
                            return;
                        }

                        // Formato CPF: XXX.XXX.XXX-XX
                        if (!preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $value)) {
                            $fail('O CPF deve ter o formato XXX.XXX.XXX-XX.');
                            return;
                        }
                    }
                },
            ],
            'path_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'path_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'nicho_id' => 'required|integer|exists:nichos_empresa,id',
            'ativo' => 'boolean',

            // Endereço da empresa
            'endereco' => 'required|array',
            'endereco.logradouro' => 'required|string|max:255',
            'endereco.numero' => 'required|string|max:20',
            'endereco.complemento' => 'nullable|string|max:255',
            'endereco.bairro' => 'nullable|string|max:255',
            'endereco.cidade' => 'nullable|string|max:255',
            'endereco.estado' => 'nullable|string|size:2',
            'endereco.cep' => 'nullable|string|regex:/^\d{5}-\d{3}$/',
            'endereco.ponto_referencia' => 'nullable|string|max:500',
            'endereco.observacoes' => 'nullable|string|max:500',

            // Configurações da empresa (opcional)
            'configuracoes' => 'nullable|array',
            'configuracoes.faz_entrega' => 'nullable|boolean',
            'configuracoes.faz_retirada' => 'nullable|boolean',
            'configuracoes.a_combinar' => 'nullable|boolean',
            'configuracoes.valor_entrega_padrao' => 'nullable|numeric|min:0|max:999999.99',
            'configuracoes.valor_entrega_minimo' => 'nullable|numeric|min:0|max:999999.99',
            'configuracoes.telefone_comercial' => 'nullable|string|max:20',
            'configuracoes.celular_comercial' => 'nullable|string|max:20',
            'configuracoes.whatsapp_pedidos' => 'nullable|string|max:20',
            'configuracoes.email' => 'nullable|email|max:255',
            'configuracoes.facebook' => 'nullable|url|max:500',
            'configuracoes.instagram' => 'nullable|url|max:500',
            'configuracoes.linkedin' => 'nullable|url|max:500',
            'configuracoes.youtube' => 'nullable|url|max:500',
            'configuracoes.tiktok' => 'nullable|url|max:500',

            // Horários de funcionamento (opcional)
            'horarios' => 'nullable|array',
            'horarios.*.dia_semana' => 'nullable|string|in:segunda,terca,quarta,quinta,sexta,sabado,domingo',
            'horarios.*.slug' => 'nullable|string|max:50',
            'horarios.*.horario_inicio' => 'nullable|date_format:H:i',
            'horarios.*.horario_fim' => 'nullable|date_format:H:i',
            'horarios.*.padrao' => 'nullable|boolean',

            // Assinatura (opcional)
            'assinatura' => 'nullable|array',
            'assinatura.plano_id' => 'nullable|integer|exists:planos,id',
            'assinatura.data_inicio' => 'nullable|date',
            'assinatura.data_fim' => 'nullable|date|after:assinatura.data_inicio',
            'assinatura.valor' => 'nullable|numeric|min:0|max:999999.99',
            'assinatura.ativo' => 'nullable|boolean',

            // Formas de pagamento (opcional)
            'formas_pagamento' => 'nullable|array',
            'formas_pagamento.*.forma_pagamento_id' => 'nullable|integer|exists:formas_pagamentos,id',
            'formas_pagamento.*.ativo' => 'nullable|boolean',

            // Bairros de entrega
            'bairros_entrega' => 'nullable|array',
            'bairros_entrega.*.bairro_id' => 'required|integer|exists:bairros,id',
            'bairros_entrega.*.valor_entrega' => 'nullable|numeric|min:0|max:999999.99',
            'bairros_entrega.*.valor_entrega_minimo' => 'nullable|numeric|min:0|max:999999.99',
            'bairros_entrega.*.ativo' => 'boolean',

            // Usuário admin da empresa (obrigatório apenas quando não é filial)
            'usuario_admin' => $this->boolean('is_filial') ? 'nullable|array' : 'required|array',
            'usuario_admin.nome' => $this->boolean('is_filial') ? 'nullable|string|max:255' : 'required|string|max:255',
            'usuario_admin.telefone' => $this->boolean('is_filial') ? 'nullable|string|max:255' : 'required|string|max:255',
            'usuario_admin.email' => $this->boolean('is_filial') ? 'nullable|email|max:255' : [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (\App\Models\User::where('email', $value)->where('tipo_cadastro', 0)->exists()) {
                        $fail('Este email já está sendo usado por outro usuário.');
                    }
                },
            ],
            'usuario_admin.password' => $this->boolean('is_filial') ? 'nullable|string|min:8|max:255' : 'required|string|min:8|max:255',
            'usuario_admin.password_confirmation' => $this->boolean('is_filial') ? 'nullable|string|same:usuario_admin.password' : 'required|string|same:usuario_admin.password',
            'usuario_admin.permissoes' => $this->boolean('is_filial') ? 'nullable|array' : 'required|array',
            'usuario_admin.permissoes.*' => 'exists:permissoes,id',
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
            // Campos principais da empresa
            'razao_social.required' => 'A razão social da empresa é obrigatória.',
            'razao_social.string' => 'A razão social deve ser um texto válido.',
            'razao_social.max' => 'A razão social não pode ter mais que 255 caracteres.',
            'razao_social.unique' => 'Esta razão social já está sendo usada por outra empresa.',

            'nome_fantasia.string' => 'O nome fantasia deve ser um texto válido.',
            'nome_fantasia.max' => 'O nome fantasia não pode ter mais que 255 caracteres.',

            'slug.string' => 'O slug deve ser um texto válido.',
            'slug.max' => 'O slug não pode ter mais que 255 caracteres.',
            'slug.unique' => 'Este slug já está sendo usado por outra empresa.',

            'email.required' => 'O email da empresa é obrigatório.',
            'email.email' => 'O email deve ter um formato válido.',
            'email.max' => 'O email não pode ter mais que 255 caracteres.',

            'telefone.required' => 'O telefone da empresa é obrigatório.',
            'telefone.string' => 'O telefone deve ser um texto válido.',
            'telefone.max' => 'O telefone não pode ter mais que 20 caracteres.',

            'tipo_pessoa.required' => 'O tipo de pessoa é obrigatório.',
            'tipo_pessoa.boolean' => 'O tipo de pessoa deve ser 0 (Jurídica) ou 1 (Física).',
            'tipo_pessoa.in' => 'O tipo de pessoa deve ser 0 (Jurídica) ou 1 (Física).',

            'cpf_cnpj.required' => 'O CPF/CNPJ da empresa é obrigatório.',
            'cpf_cnpj.unique' => 'Este CPF/CNPJ já está sendo usado por outra empresa.',

            'path_logo.image' => 'A logo deve ser uma imagem válida.',
            'path_logo.mimes' => 'A logo deve ser um arquivo do tipo: jpeg, png, jpg, gif.',
            'path_logo.max' => 'A logo não pode ter mais que 2MB.',

            'path_banner.image' => 'O banner deve ser uma imagem válida.',
            'path_banner.mimes' => 'O banner deve ser um arquivo do tipo: jpeg, png, jpg, gif.',
            'path_banner.max' => 'O banner não pode ter mais que 5MB.',

            'nicho_id.required' => 'O nicho da empresa é obrigatório.',
            'nicho_id.integer' => 'O nicho deve ser um número inteiro.',
            'nicho_id.exists' => 'O nicho selecionado não existe.',

            'ativo.boolean' => 'O campo ativo deve ser verdadeiro ou falso.',

            // Endereço da empresa
            'endereco.required' => 'Os dados de endereço são obrigatórios.',
            'endereco.array' => 'Os dados de endereço devem ser um objeto válido.',

            'endereco.logradouro.required' => 'O logradouro é obrigatório.',
            'endereco.logradouro.string' => 'O logradouro deve ser um texto válido.',
            'endereco.logradouro.max' => 'O logradouro não pode ter mais que 255 caracteres.',

            'endereco.numero.required' => 'O número é obrigatório.',
            'endereco.numero.string' => 'O número deve ser um texto válido.',
            'endereco.numero.max' => 'O número não pode ter mais que 20 caracteres.',

            'endereco.complemento.string' => 'O complemento deve ser um texto válido.',
            'endereco.complemento.max' => 'O complemento não pode ter mais que 255 caracteres.',

            'endereco.bairro.string' => 'O bairro deve ser um texto válido.',
            'endereco.bairro.max' => 'O bairro não pode ter mais que 255 caracteres.',

            'endereco.cidade.string' => 'A cidade deve ser um texto válido.',
            'endereco.cidade.max' => 'A cidade não pode ter mais que 255 caracteres.',

            'endereco.estado.string' => 'O estado deve ser um texto válido.',
            'endereco.estado.size' => 'O estado deve ter exatamente 2 caracteres (sigla).',

            'endereco.cep.regex' => 'O CEP deve ter o formato XXXXX-XXX.',

            'endereco.ponto_referencia.string' => 'O ponto de referência deve ser um texto válido.',
            'endereco.ponto_referencia.max' => 'O ponto de referência não pode ter mais que 500 caracteres.',

            'endereco.observacoes.string' => 'As observações devem ser um texto válido.',
            'endereco.observacoes.max' => 'As observações não podem ter mais que 500 caracteres.',

            // Configurações da empresa
            'configuracoes.array' => 'As configurações devem ser um objeto válido.',

            'configuracoes.faz_entrega.boolean' => 'O campo "faz entrega" deve ser verdadeiro ou falso.',
            'configuracoes.faz_retirada.boolean' => 'O campo "faz retirada" deve ser verdadeiro ou falso.',
            'configuracoes.a_combinar.boolean' => 'O campo "a combinar" deve ser verdadeiro ou falso.',

            'configuracoes.valor_entrega_padrao.numeric' => 'O valor padrão de entrega deve ser um número.',
            'configuracoes.valor_entrega_padrao.min' => 'O valor padrão de entrega deve ser maior ou igual a zero.',
            'configuracoes.valor_entrega_padrao.max' => 'O valor padrão de entrega não pode ser maior que 999.999,99.',

            'configuracoes.valor_entrega_minimo.numeric' => 'O valor mínimo de entrega deve ser um número.',
            'configuracoes.valor_entrega_minimo.min' => 'O valor mínimo de entrega deve ser maior ou igual a zero.',
            'configuracoes.valor_entrega_minimo.max' => 'O valor mínimo de entrega não pode ser maior que 999.999,99.',

            'configuracoes.telefone_comercial.string' => 'O telefone comercial deve ser um texto válido.',
            'configuracoes.telefone_comercial.max' => 'O telefone comercial não pode ter mais que 20 caracteres.',

            'configuracoes.celular_comercial.string' => 'O celular comercial deve ser um texto válido.',
            'configuracoes.celular_comercial.max' => 'O celular comercial não pode ter mais que 20 caracteres.',

            'configuracoes.whatsapp_pedidos.string' => 'O WhatsApp de pedidos deve ser um texto válido.',
            'configuracoes.whatsapp_pedidos.max' => 'O WhatsApp de pedidos não pode ter mais que 20 caracteres.',

            'configuracoes.email.email' => 'O email das configurações deve ter um formato válido.',
            'configuracoes.email.max' => 'O email das configurações não pode ter mais que 255 caracteres.',

            'configuracoes.facebook.url' => 'O link do Facebook deve ser uma URL válida.',
            'configuracoes.facebook.max' => 'O link do Facebook não pode ter mais que 500 caracteres.',

            'configuracoes.instagram.url' => 'O link do Instagram deve ser uma URL válida.',
            'configuracoes.instagram.max' => 'O link do Instagram não pode ter mais que 500 caracteres.',

            'configuracoes.linkedin.url' => 'O link do LinkedIn deve ser uma URL válida.',
            'configuracoes.linkedin.max' => 'O link do LinkedIn não pode ter mais que 500 caracteres.',

            'configuracoes.youtube.url' => 'O link do YouTube deve ser uma URL válida.',
            'configuracoes.youtube.max' => 'O link do YouTube não pode ter mais que 500 caracteres.',

            'configuracoes.tiktok.url' => 'O link do TikTok deve ser uma URL válida.',
            'configuracoes.tiktok.max' => 'O link do TikTok não pode ter mais que 500 caracteres.',

            // Horários de funcionamento
            'horarios.array' => 'Os horários devem ser uma lista válida.',

            'horarios.*.dia_semana.string' => 'O dia da semana deve ser um texto válido.',
            'horarios.*.dia_semana.in' => 'O dia da semana deve ser: segunda, terca, quarta, quinta, sexta, sabado ou domingo.',

            'horarios.*.slug.string' => 'O slug do horário deve ser um texto válido.',
            'horarios.*.slug.max' => 'O slug do horário não pode ter mais que 50 caracteres.',

            'horarios.*.horario_inicio.date_format' => 'O horário de início deve ter o formato HH:MM.',

            'horarios.*.horario_fim.date_format' => 'O horário de fim deve ter o formato HH:MM.',

            'horarios.*.padrao.boolean' => 'O campo padrão deve ser verdadeiro ou falso.',

            // Assinatura
            'assinatura.array' => 'Os dados de assinatura devem ser um objeto válido.',

            'assinatura.plano_id.integer' => 'O plano deve ser um número inteiro.',
            'assinatura.plano_id.exists' => 'O plano selecionado não existe.',

            'assinatura.data_inicio.date' => 'A data de início deve ser uma data válida.',

            'assinatura.data_fim.date' => 'A data de fim deve ser uma data válida.',
            'assinatura.data_fim.after' => 'A data de fim deve ser posterior à data de início.',

            'assinatura.valor.numeric' => 'O valor da assinatura deve ser um número.',
            'assinatura.valor.min' => 'O valor da assinatura deve ser maior ou igual a zero.',
            'assinatura.valor.max' => 'O valor da assinatura não pode ser maior que 999.999,99.',

            'assinatura.ativo.boolean' => 'O campo ativo da assinatura deve ser verdadeiro ou falso.',

            // Formas de pagamento
            'formas_pagamento.array' => 'As formas de pagamento devem ser uma lista válida.',

            'formas_pagamento.*.forma_pagamento_id.integer' => 'A forma de pagamento deve ser um número inteiro.',
            'formas_pagamento.*.forma_pagamento_id.exists' => 'A forma de pagamento selecionada não existe.',

            'formas_pagamento.*.ativo.boolean' => 'O campo ativo da forma de pagamento deve ser verdadeiro ou falso.',

            // Bairros de entrega
            'bairros_entrega.array' => 'Os bairros de entrega devem ser uma lista válida.',

            'bairros_entrega.*.bairro_id.required' => 'O bairro é obrigatório.',
            'bairros_entrega.*.bairro_id.integer' => 'O bairro deve ser um número inteiro.',
            'bairros_entrega.*.bairro_id.exists' => 'O bairro selecionado não existe.',

            'bairros_entrega.*.valor_entrega.numeric' => 'O valor de entrega do bairro deve ser um número.',
            'bairros_entrega.*.valor_entrega.min' => 'O valor de entrega do bairro deve ser maior ou igual a zero.',
            'bairros_entrega.*.valor_entrega.max' => 'O valor de entrega do bairro não pode ser maior que 999.999,99.',

            'bairros_entrega.*.valor_entrega_minimo.numeric' => 'O valor mínimo de entrega do bairro deve ser um número.',
            'bairros_entrega.*.valor_entrega_minimo.min' => 'O valor mínimo de entrega do bairro deve ser maior ou igual a zero.',
            'bairros_entrega.*.valor_entrega_minimo.max' => 'O valor mínimo de entrega do bairro não pode ser maior que 999.999,99.',

            'bairros_entrega.*.ativo.boolean' => 'O campo ativo do bairro deve ser verdadeiro ou falso.',

            // Usuário admin da empresa
            'usuario_admin.required' => 'Os dados do usuário administrador são obrigatórios.',
            'usuario_admin.array' => 'Os dados do usuário administrador devem ser um objeto válido.',

            'usuario_admin.nome.required' => 'O nome do usuário administrador é obrigatório.',
            'usuario_admin.nome.string' => 'O nome do usuário administrador deve ser um texto válido.',
            'usuario_admin.nome.max' => 'O nome do usuário administrador não pode ter mais que 255 caracteres.',

            'usuario_admin.telefone.required' => 'O telefone do usuário administrador é obrigatório.',
            'usuario_admin.telefone.string' => 'O telefone do usuário administrador deve ser um texto válido.',
            'usuario_admin.telefone.max' => 'O telefone do usuário administrador não pode ter mais que 255 caracteres.',

            'usuario_admin.email.required' => 'O email do usuário administrador é obrigatório.',
            'usuario_admin.email.email' => 'O email do usuário administrador deve ter um formato válido.',
            'usuario_admin.email.max' => 'O email do usuário administrador não pode ter mais que 255 caracteres.',
            'usuario_admin.email.unique' => 'Este email já está sendo usado por outro usuário.',

            'usuario_admin.password.required' => 'A senha do usuário administrador é obrigatória.',
            'usuario_admin.password.string' => 'A senha deve ser um texto válido.',
            'usuario_admin.password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'usuario_admin.password.max' => 'A senha não pode ter mais que 255 caracteres.',

            'usuario_admin.password_confirmation.required' => 'A confirmação da senha é obrigatória.',
            'usuario_admin.password_confirmation.string' => 'A confirmação da senha deve ser um texto válido.',
            'usuario_admin.password_confirmation.same' => 'A confirmação da senha deve ser igual à senha.',

            'usuario_admin.permissoes.required' => 'As permissões do usuário administrador são obrigatórias.',
            'usuario_admin.permissoes.array' => 'As permissões devem ser enviadas como um array.',
            'usuario_admin.permissoes.*.exists' => 'Uma ou mais permissões selecionadas não existem.',
        ];
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
