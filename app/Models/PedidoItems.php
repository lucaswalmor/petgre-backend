<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class PedidoItems extends Model
{
    use HasFactory;
    protected $table = 'pedido_items';

    protected $fillable = [
        'pedido_id',
        'produto_id',
        'kit_id',
        'quantidade',
        'preco_unitario',
        'preco_total',
        'desconto',
        'observacoes',
        'ativo',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function kit()
    {
        return $this->belongsTo(Kit::class, 'kit_id');
    }
}
