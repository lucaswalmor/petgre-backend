<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpresaResource extends JsonResource
{
    /**
     * Dados adicionais para incluir na resposta
     */
    private array $additionalData = [];

    /**
     * Constructor to accept additional data
     */
    public function __construct($resource, array $additionalData = [])
    {
        parent::__construct($resource);
        $this->additionalData = $additionalData;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_pessoa' => $this->tipo_pessoa,
            'razao_social' => $this->razao_social,
            'nome_fantasia' => $this->nome_fantasia,
            'slug' => $this->slug,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'cpf_cnpj' => $this->cpf_cnpj,
            'path_logo' => $this->path_logo,
            'path_banner' => $this->path_banner,
            'ativo' => $this->ativo,
            'cadastro_completo' => $this->cadastro_completo,

            // Relacionamentos
            'nicho' => $this->whenLoaded('nicho', function () {
                return [
                    'id' => $this->nicho->id,
                    'nome' => $this->nicho->nome,
                    'slug' => $this->nicho->slug,
                ];
            }),

            'endereco' => $this->whenLoaded('endereco', function () {
                return [
                    'id' => $this->endereco->id,
                    'logradouro' => $this->endereco->logradouro,
                    'numero' => $this->endereco->numero,
                    'complemento' => $this->endereco->complemento,
                    'bairro' => $this->endereco->bairro,
                    'cidade' => $this->endereco->cidade,
                    'estado' => $this->endereco->estado,
                    'cep' => $this->endereco->cep,
                    'ponto_referencia' => $this->endereco->ponto_referencia,
                    'observacoes' => $this->endereco->observacoes,
                ];
            }),

            'configuracoes' => $this->whenLoaded('configuracoes', function () {
                return [
                    'id' => $this->configuracoes->id,
                    'faz_entrega' => $this->configuracoes->faz_entrega,
                    'faz_retirada' => $this->configuracoes->faz_retirada,
                    'a_combinar' => $this->configuracoes->a_combinar,
                    'valor_entrega_padrao' => $this->configuracoes->valor_entrega_padrao,
                    'valor_entrega_minimo' => $this->configuracoes->valor_entrega_minimo,
                    'telefone_comercial' => $this->configuracoes->telefone_comercial,
                    'celular_comercial' => $this->configuracoes->celular_comercial,
                    'whatsapp_pedidos' => $this->configuracoes->whatsapp_pedidos,
                    'email' => $this->configuracoes->email,
                    'facebook' => $this->configuracoes->facebook,
                    'instagram' => $this->configuracoes->instagram,
                    'linkedin' => $this->configuracoes->linkedin,
                    'youtube' => $this->configuracoes->youtube,
                    'tiktok' => $this->configuracoes->tiktok,
                ];
            }),

            'horarios' => $this->whenLoaded('horarios', function () {
                return $this->horarios->map(function ($horario) {
                    return [
                        'id' => $horario->id,
                        'dia_semana' => $horario->dia_semana,
                        'slug' => $horario->slug,
                        'horario_inicio' => $horario->horario_inicio,
                        'horario_fim' => $horario->horario_fim,
                        'padrao' => $horario->padrao,
                    ];
                });
            }),

            'assinatura' => $this->whenLoaded('assinatura', function () {
                return [
                    'id' => $this->assinatura->id,
                    'plano' => $this->whenLoaded('assinatura.plano', function () {
                        return [
                            'id' => $this->assinatura->plano->id,
                            'nome' => $this->assinatura->plano->nome,
                            'valor' => $this->assinatura->plano->valor,
                        ];
                    }),
                    'data_inicio' => $this->assinatura->data_inicio,
                    'data_fim' => $this->assinatura->data_fim,
                    'valor' => $this->assinatura->valor,
                    'ativo' => $this->assinatura->ativo,
                ];
            }),

            'formas_pagamento' => $this->whenLoaded('formasPagamentos', function () {
                return $this->formasPagamentos->map(function ($forma) {
                    return [
                        'id' => $forma->id,
                        'forma_pagamento' => [
                            'id' => $forma->forma_pagamento_id,
                            'nome' => $forma->formaPagamento->nome,
                            'slug' => $forma->formaPagamento->slug,
                        ],
                        'ativo' => $forma->ativo,
                    ];
                });
            }),

            'bairros_entrega' => $this->whenLoaded('bairrosEntregas', function () {
                return $this->bairrosEntregas->map(function ($bairro) {
                    return [
                        'id' => $bairro->id,
                        'bairro' => [
                            'id' => $bairro->bairro_id,
                            'nome' => $bairro->bairro->nome,
                        ],
                        'valor_entrega' => $bairro->valor_entrega,
                        'valor_entrega_minimo' => $bairro->valor_entrega_minimo,
                        'ativo' => $bairro->ativo,
                    ];
                });
            }),

            'usuarios_count' => $this->whenLoaded('usuarios', function () {
                return $this->usuarios->count();
            }),

            'usuarios' => $this->whenLoaded('usuarios', function () {
                return $this->usuarios->map(function ($usuario) {
                    return [
                        'id' => $usuario->usuario->id,
                        'nome' => $usuario->usuario->nome,
                        'email' => $usuario->usuario->email,
                        'ativo' => $usuario->usuario->ativo,
                        'permissao' => $usuario->usuario->permissao ? [
                            'id' => $usuario->usuario->permissao->id,
                            'nome' => $usuario->usuario->permissao->nome,
                            'slug' => $usuario->usuario->permissao->slug,
                        ] : null,
                    ];
                });
            }),

            // Incluir dados adicionais se existirem
            ...$this->additionalData,
        ];
    }
}
