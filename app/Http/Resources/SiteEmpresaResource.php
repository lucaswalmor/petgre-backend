<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class SiteEmpresaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dados = [
            'id' => $this->id,
            'razao_social' => $this->razao_social,
            'nome_fantasia' => $this->nome_fantasia,
            'slug' => $this->slug,
            'path_logo' => $this->path_logo ? $this->path_logo : null,
            'path_banner' => $this->path_banner ? $this->path_banner : null,
            // 'path_logo' => $this->path_logo ? asset('storage/' . $this->path_logo) : null,
            // 'path_banner' => $this->path_banner ? asset('storage/' . $this->path_banner) : null,
            'ativo' => $this->ativo,
            'empresa_aberta' => $this->resource->isAberta(),
            'fechado_ate' => $this->resource->getFechadoAte(),
            'horario_hoje' => $this->getHorarioHoje(),
            'empresa_nova' => $this->created_at >= now()->subMonth(),
        ];

        // Verificar se a empresa está nos favoritos do usuário autenticado
        $usuario = auth('sanctum')->user();
        $dados['has_favorite'] = $usuario ? $this->empresaFavoritos()->where('usuario_id', $usuario->id)->exists() : false;

        // Média de avaliações
        if ($this->relationLoaded('avaliacoes')) {
            $media = $this->avaliacoes()->selectRaw('AVG(nota) as media, COUNT(*) as total')->first();
            $dados['nota_media'] = $media ? round($media->media, 1) : 0;
            $dados['total_avaliacoes'] = $media ? $media->total : 0;
        }

        // Nicho
        if ($this->relationLoaded('nicho')) {
            $dados['nicho'] = [
                'id' => $this->nicho->id,
                'nome' => $this->nicho->nome,
                'imagem' => $this->nicho->imagem ? $this->nicho->imagem : null,
                'slug' => $this->nicho->slug,
            ];
        }

        // Informações públicas da empresa para clientes
        $dados['endereco'] = $this->whenLoaded('endereco', function () {
            return [
                'logradouro' => $this->endereco->logradouro,
                'numero' => $this->endereco->numero,
                'bairro' => $this->endereco->bairro,
                'cidade' => $this->endereco->cidade,
                'estado' => $this->endereco->estado,
                'cep' => $this->endereco->cep,
            ];
        });

        $dados['horarios'] = $this->whenLoaded('horarios', function () {
            return $this->horarios->map(function ($horario) {
                return [
                    'dia_semana' => $horario->dia_semana,
                    'horario_inicio' => $horario->horario_inicio,
                    'horario_fim' => $horario->horario_fim,
                ];
            });
        });

        $dados['bairros_entrega'] = $this->whenLoaded('bairrosEntregas', function () {
            return $this->bairrosEntregas->map(function ($bairro) {
                return [
                    'id' => $bairro->id,
                    'nome' => $bairro->bairro->nome,
                    'valor_entrega' => $bairro->valor_entrega,
                    'valor_entrega_minimo' => $bairro->valor_entrega_minimo,
                ];
            });
        });

        $dados['formas_pagamento'] = $this->whenLoaded('formasPagamentos', function () {
            return $this->formasPagamentos->map(function ($forma) {
                return [
                    'id' => $forma->forma_pagamento_id,
                    'nome' => $forma->formaPagamento->nome,
                    'slug' => $forma->formaPagamento->slug,
                ];
            });
        });

        $dados['configuracoes'] = $this->whenLoaded('configuracoes', function () {
            return [
                'faz_entrega' => $this->configuracoes->faz_entrega,
                'faz_retirada' => $this->configuracoes->faz_retirada,
                'valor_entrega_padrao' => $this->configuracoes->valor_entrega_padrao,
                'valor_entrega_minimo' => $this->configuracoes->valor_entrega_minimo,
                'whatsapp_pedidos' => $this->configuracoes->whatsapp_pedidos,
                'whatsapp_pedidos_formatado' => $this->configuracoes->whatsapp_pedidos ? preg_replace('/[^\d]/', '', $this->configuracoes->whatsapp_pedidos) : null,
                'telefone_comercial' => $this->configuracoes->telefone_comercial,
                'celular_comercial' => $this->configuracoes->celular_comercial,
                'email' => $this->configuracoes->email,
                'facebook' => $this->configuracoes->facebook,
                'instagram' => $this->configuracoes->instagram,
                'linkedin' => $this->configuracoes->linkedin,
                'youtube' => $this->configuracoes->youtube,
                'tiktok' => $this->configuracoes->tiktok,
                'aceita_cupons_sistema' => $this->configuracoes->aceita_cupons_sistema ?? false,
            ];
        });

        $dados['produtos'] = $this->whenLoaded('produtos', function () {
            $produtosAtivos = $this->produtos->where('ativo', true)
                ->where('tipo', '!=', 'servico') // Apenas produtos físicos (excluir serviços)
                ->filter(function ($produto) {
                    return $produto->estoque !== null && (float) $produto->estoque > 0;
                });

            // Agrupar produtos por categoria
            $produtosPorCategoria = $produtosAtivos->groupBy(function ($produto) {
                return $produto->categoria ? $produto->categoria->nome : 'Sem Categoria';
            })->map(function ($produtos, $categoriaNome) {
                return [
                    'categoria' => $categoriaNome,
                    'produtos' => \App\Http\Resources\Produto\ProdutoResource::collection($produtos)
                ];
            })->values();

            return $produtosPorCategoria;
        });

        // Serviços separados (banho, tosa, etc.)
        $dados['servicos'] = $this->whenLoaded('produtos', function () {
            $servicosAtivos = $this->produtos->where('ativo', true)
                ->where('tipo', 'servico')
                ->values();

            return $servicosAtivos->map(function ($servico) {
                return [
                    'id' => $servico->id,
                    'nome' => $servico->nome,
                    'descricao' => $servico->descricao,
                    'tipo' => 'servico',
                    'tipo_porte' => $servico->tipo_porte,
                    'preco' => $servico->preco,
                    'preco_pequeno' => $servico->preco_pequeno,
                    'preco_medio' => $servico->preco_medio,
                    'preco_grande' => $servico->preco_grande,
                    'porte_descricao_pequeno' => $servico->porte_descricao_pequeno,
                    'porte_descricao_medio' => $servico->porte_descricao_medio,
                    'porte_descricao_grande' => $servico->porte_descricao_grande,
                    'duracao_estimada' => $servico->duracao_estimada,
                    'inclui_servico' => $servico->inclui_servico,
                    'preco_atual' => $servico->tipo_porte === 'todos' ? $servico->preco_pequeno : $servico->preco,
                    'preco_formatado' => 'R$ ' . number_format($servico->preco, 2, ',', '.'),
                    'url_imagem' => $servico->url_imagem,
                    'categoria' => $servico->categoria ? [
                        'id' => $servico->categoria->id,
                        'nome' => $servico->categoria->nome
                    ] : null,
                ];
            });
        });

        // Produtos em destaque (para carrossel no app cliente), limitado a 12 — apenas com estoque
        $dados['destaques'] = $this->whenLoaded('produtos', function () {
            $destaques = $this->produtos
                ->where('ativo', true)
                ->where('destaque', true)
                ->filter(function ($produto) {
                    if ($produto->tipo === 'servico') {
                        return true;
                    }
                    return $produto->estoque !== null && (float) $produto->estoque > 0;
                })
                ->take(12)
                ->values();
            return \App\Http\Resources\Produto\ProdutoResource::collection($destaques);
        });

        $dados['kits'] = $this->whenLoaded('kits', function () {
            $kitsAtivos = $this->kits->where('ativo', true);

            return $kitsAtivos->map(function ($kit) {
                $itens = $kit->relationLoaded('itens') ? $kit->itens : $kit->itens()->with('produto')->get();

                $precoSomaItens = 0;
                $itensFormatados = [];
                $quantidadeMaximaKit = null;

                foreach ($itens as $item) {
                    $produto = $item->relationLoaded('produto') ? $item->produto : $item->produto;
                    $precoProduto = $produto ? (float) $produto->preco : 0;
                    $qtdItem = (float) $item->quantidade;
                    $precoSomaItens += $precoProduto * $qtdItem;

                    $itensFormatados[] = [
                        'produto_id' => $item->produto_id,
                        'nome_produto' => $produto ? $produto->nome : null,
                        'url_imagem' => $produto && $produto->imagem ? $produto->imagem : null,
                        'quantidade' => (int) $qtdItem,
                        'preco_produto' => $precoProduto,
                        'preco_produto_formatado' => $precoProduto
                            ? 'R$ ' . number_format($precoProduto, 2, ',', '.')
                            : null,
                    ];

                    // Estoque disponível para este item: serviço não limita; senão quantos kits cabem (estoque e qtd por kit na mesma unidade)
                    if ($produto && $produto->tipo !== 'servico' && $produto->estoque !== null && $qtdItem > 0) {
                        $maxPorItem = (int) floor((float) $produto->estoque / $qtdItem);
                        $quantidadeMaximaKit = $quantidadeMaximaKit === null ? $maxPorItem : min($quantidadeMaximaKit, $maxPorItem);
                    }
                }

                $precoKit = (float) $kit->preco;
                $temEstoque = $quantidadeMaximaKit === null || $quantidadeMaximaKit > 0;

                return [
                    'id' => $kit->id,
                    'nome' => $kit->nome,
                    'descricao' => $kit->descricao,
                    'imagem' => $kit->imagem,
                    'preco' => $precoKit,
                    'preco_formatado' => 'R$ ' . number_format($precoKit, 2, ',', '.'),
                    'itens' => $itensFormatados,
                    'preco_soma_itens' => $precoSomaItens,
                    'preco_soma_itens_formatado' => 'R$ ' . number_format($precoSomaItens, 2, ',', '.'),
                    'quantidade_maxima' => $quantidadeMaximaKit ?? 999999,
                    'tem_estoque' => $temEstoque,
                ];
            })->filter(function ($kit) {
                return $kit['tem_estoque'];
            })->values();
        });

        $dados['avaliacoes_recentes'] = $this->whenLoaded('avaliacoes', function () {
            return $this->avaliacoes->map(function ($avaliacao) {
                return [
                    'id' => $avaliacao->id,
                    'nota' => $avaliacao->nota,
                    'comentario' => $avaliacao->descricao,
                    'created_at' => $avaliacao->created_at->format('d/m/Y H:i')
                ];
            });
        });

        return $dados;
    }

    /**
     * Obtém o horário de funcionamento do dia atual
     */
    private function getHorarioHoje(): ?string
    {
        // Obter o dia da semana (0=domingo, 1=segunda, ..., 6=sábado)
        $dias = [
            0 => 'domingo',
            1 => 'segunda',
            2 => 'terca',
            3 => 'quarta',
            4 => 'quinta',
            5 => 'sexta',
            6 => 'sabado',
        ];

        $hojeNumero = now()->dayOfWeek;
        $hojeTexto = isset($dias[$hojeNumero]) ? $dias[$hojeNumero] : null;

        if (!$hojeTexto) {
            return null;
        }

        // Busca a relação 'horarios', caso exista
        $horarios = $this->relationLoaded('horarios') ? $this->horarios : ($this->horarios ?? collect());

        if (!$horarios || $horarios->isEmpty()) {
            return null;
        }

        $horarioHoje = $horarios->first(function($horario) use ($hojeTexto) {
            return strtolower($horario->dia_semana) === $hojeTexto;
        });

        if ($horarioHoje && $horarioHoje->horario_inicio && $horarioHoje->horario_fim) {
            $horarioInicio = Carbon::createFromFormat('H:i:s', $horarioHoje->horario_inicio)->format('H:i');
            $horarioFim = Carbon::createFromFormat('H:i:s', $horarioHoje->horario_fim)->format('H:i');
            return $horarioInicio . ' - ' . $horarioFim;
        }

        return null;
    }

}