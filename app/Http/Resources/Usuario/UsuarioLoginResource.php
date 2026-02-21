<?php

namespace App\Http\Resources\Usuario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioLoginResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'ativo' => $this->ativo,
            'primeiro_login' => (bool) $this->primeiro_login,
            'permissoes' => $this->whenLoaded('permissoes', function () {
                return $this->permissoes->map(function ($permissao) {
                    return [
                        'id' => $permissao->id,
                        'nome' => $permissao->nome,
                        'slug' => $permissao->slug,
                    ];
                });
            }, []),
            'empresas' => $this->whenLoaded('empresas', function () {
                return $this->empresas->map(function ($empresa) {
                    return [
                        'id' => $empresa->id,
                        'razao_social' => $empresa->razao_social,
                        'nome_fantasia' => $empresa->nome_fantasia,
                        'slug' => $empresa->slug,
                        'ativo' => $empresa->ativo,
                    ];
                });
            }, []),
            'enderecos' => $this->whenLoaded('enderecos', function () {
                return $this->enderecos->where('ativo', true)->map(function ($endereco) {
                    return [
                        'id' => $endereco->id,
                        'cep' => $endereco->cep,
                        'rua' => $endereco->rua,
                        'numero' => $endereco->numero,
                        'complemento' => $endereco->complemento,
                        'bairro' => $endereco->bairro,
                        'cidade' => $endereco->cidade,
                        'estado' => $endereco->estado,
                        'ponto_referencia' => $endereco->ponto_referencia,
                        'observacoes' => $endereco->observacoes,
                        'endereco_padrao' => $endereco->endereco_padrao ?? false,
                    ];
                });
            }, []),
        ];
    }
}
