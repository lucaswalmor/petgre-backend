<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Produto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'produtos';

    protected $fillable = [
        'empresa_id',
        'categoria_id',
        'unidade_medida_id',
        'tipo',
        'nome',
        'imagem',
        'slug',
        'descricao',
        'preco',
        'estoque',
        'destaque',
        'ativo',
        'marca',
        'sku',
        'preco_custo',
        'estoque_minimo',
        'peso',
        'altura',
        'largura',
        'comprimento',
        'ordem',
        'preco_promocional',
        'promocao_ate',
        'tem_promocao',
        'vende_granel',
    ];

    protected $casts = [
        'preco' => 'decimal:2',
        'estoque' => 'decimal:3',
        'destaque' => 'boolean',
        'ativo' => 'boolean',
        'preco_custo' => 'decimal:2',
        'estoque_minimo' => 'decimal:3',
        'peso' => 'decimal:3',
        'altura' => 'decimal:2',
        'largura' => 'decimal:2',
        'comprimento' => 'decimal:2',
        'ordem' => 'integer',
        'preco_promocional' => 'decimal:2',
        'promocao_ate' => 'date',
        'tem_promocao' => 'boolean',
        'vende_granel' => 'boolean',
    ];

    // Boot method para gerar slug automaticamente
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($produto) {
            if (empty($produto->slug)) {
                $produto->slug = Str::slug($produto->nome);
            }
        });

        static::updating(function ($produto) {
            if ($produto->isDirty('nome') && empty($produto->slug)) {
                $produto->slug = Str::slug($produto->nome);
            }
        });
    }

    // Relação com itens do pedido
    public function itens()
    {
        return $this->hasMany(PedidoItems::class, 'produto_id');
    }

    // Relação com empresa
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // Relação com categoria
    public function categoria()
    {
        return $this->belongsTo(Categorias::class, 'categoria_id');
    }

    // Relação com unidade de medida
    public function unidadeMedida()
    {
        return $this->belongsTo(UnidadeMedida::class, 'unidade_medida_id');
    }

    // Scope para produtos ativos
    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    // Scope para produtos em destaque
    public function scopeDestaque($query)
    {
        return $query->where('destaque', true);
    }

    // Scope para filtrar por empresa
    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    // Scope para filtrar por categoria
    public function scopePorCategoria($query, $categoriaId)
    {
        return $query->where('categoria_id', $categoriaId);
    }

    // Scope para filtrar por tipo (produto/servico)
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    // Scope para filtrar produtos que vendem a granel
    public function scopeVendeGranel($query, $vendeGranel = true)
    {
        return $query->where('vende_granel', $vendeGranel);
    }

    // Método para verificar se tem estoque disponível
    public function temEstoque($quantidade = 1)
    {
        return $this->estoque >= $quantidade;
    }

    // Método para reduzir estoque
    public function reduzirEstoque($quantidade)
    {
        if ($this->temEstoque($quantidade)) {
            $this->estoque -= $quantidade;
            $this->save();
            return true;
        }
        return false;
    }

    // Método para adicionar estoque
    public function adicionarEstoque($quantidade)
    {
        $this->estoque += $quantidade;
        $this->save();
        return true;
    }

    // Método para verificar se está em promoção válida
    public function estaEmPromocao()
    {
        return $this->tem_promocao &&
               $this->preco_promocional &&
               (!$this->promocao_ate || $this->promocao_ate >= now());
    }

    // Método para obter preço atual (considerando promoção)
    public function getPrecoAtual()
    {
        return $this->estaEmPromocao() ? $this->preco_promocional : $this->preco;
    }

    // Método para verificar se está com estoque baixo
    public function estoqueBaixo()
    {
        return $this->estoque <= $this->estoque_minimo;
    }

    // Método para verificar se vende a granel
    public function vendeAGranel()
    {
        return $this->vende_granel;
    }

    // Método para calcular margem de lucro
    public function getMargemLucro()
    {
        if (!$this->preco_custo || $this->preco <= 0) {
            return null;
        }

        $precoAtual = $this->getPrecoAtual();

        // Evitar divisão por zero
        if ($this->preco_custo <= 0) {
            return null;
        }

        return (($precoAtual - $this->preco_custo) / $this->preco_custo) * 100;
    }
}
