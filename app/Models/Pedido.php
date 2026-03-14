<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = [
        'usuario_id',
        'empresa_id',
        'status_pedido_id',
        'tipo_pedido',
        'data_agendamento',
        'pagamento_id',
        'subtotal',
        'desconto',
        'frete',
        'total',
        'observacoes',
        'cupom_tipo',
        'cupom_id',
        'cupom_valor',
        'ativo',
        'foi_entrega',
    ];

    // Relação com usuário
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Relação com empresa
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // Relação com status do pedido
    public function statusPedido()
    {
        return $this->belongsTo(StatusPedidos::class, 'status_pedido_id');
    }

    // Relação com forma de pagamento
    public function formaPagamento()
    {
        return $this->belongsTo(FormasPagamentos::class, 'pagamento_id');
    }

    // Relação com itens do pedido
    public function itens()
    {
        return $this->hasMany(PedidoItems::class, 'pedido_id');
    }

    // Relação com endereço do pedido
    public function endereco()
    {
        return $this->hasOne(PedidoEndereco::class, 'pedido_id');
    }

    // Relação com histórico de status do pedido
    public function historicoStatus()
    {
        return $this->hasMany(PedidoHistoricoStatus::class, 'pedido_id');
    }

    // Relação com avaliação do pedido (1 por pedido)
    public function avaliacao()
    {
        return $this->hasOne(EmpresaAvaliacao::class, 'pedido_id');
    }

    // Relação com cupons de empresa usados no pedido
    public function empresaCuponsUsados()
    {
        return $this->hasMany(EmpresaCupomUsado::class, 'pedido_id');
    }

    // Relação com cupons do sistema usados no pedido
    public function sistemaCuponsUsados()
    {
        return $this->hasMany(SistemaCupomUsado::class, 'pedido_id');
    }

    // Relação com cupons de usuário usados no pedido
    public function usuarioCuponsUsados()
    {
        return $this->hasMany(UsuarioCupom::class, 'pedido_id');
    }

    /**
     * Relação com resgate de cupom do sistema (para a empresa)
     */
    public function resgateCupom()
    {
        return $this->hasOne(EmpresaResgateCupom::class, 'pedido_id');
    }

    /**
     * Verificar se pedido pode ser avaliado
     */
    public function podeSerAvaliado()
    {
        return $this->statusPedido && $this->statusPedido->slug === 'entregue' && !$this->avaliacao;
    }
}
