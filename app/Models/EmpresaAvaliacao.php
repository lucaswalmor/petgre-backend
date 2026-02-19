<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmpresaAvaliacao extends Model
{
    use HasFactory;

    protected $table = 'empresa_avaliacoes';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'pedido_id',
        'descricao',
        'nota',
    ];

    protected $casts = [
        'nota' => 'decimal:1',
    ];

    /**
     * Relacionamento com empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Relacionamento com usuário
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Relacionamento com solicitação de moderação (opcional)
     */
    public function moderacao()
    {
        return $this->hasOne(AvaliacaoModeracao::class, 'avaliacao_id');
    }

    /**
     * Relacionamento com pedido (opcional)
     */
    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    /**
     * Scope para avaliações ativas
     */
    public function scopeAtivas($query)
    {
        return $query->whereHas('empresa', function ($q) {
            $q->where('ativo', true);
        });
    }

    /**
     * Scope para filtrar por nota
     */
    public function scopePorNota($query, $nota)
    {
        return $query->where('nota', '>=', $nota);
    }

    /**
     * Scope para avaliações recentes
     */
    public function scopeRecentes($query, $dias = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }

    /**
     * Método para calcular média de avaliações de uma empresa
     */
    public static function calcularMediaEmpresa($empresaId)
    {
        return self::where('empresa_id', $empresaId)
            ->selectRaw('AVG(nota) as media, COUNT(*) as total')
            ->first();
    }

    /**
     * Método para verificar se pedido já foi avaliado
     */
    public static function pedidoJaAvaliado($pedidoId)
    {
        return self::where('pedido_id', $pedidoId)->exists();
    }

    /**
     * Método para verificar se usuário pode avaliar um pedido específico
     */
    public static function usuarioPodeAvaliarPedido($usuarioId, $pedidoId)
    {
        $pedido = \App\Models\Pedido::with('statusPedido')
            ->where('id', $pedidoId)
            ->where('usuario_id', $usuarioId)
            ->first();

        if (!$pedido) {
            return ['pode' => false, 'motivo' => 'Pedido não encontrado ou não pertence ao usuário'];
        }

        if ($pedido->statusPedido->slug !== 'entregue') {
            return ['pode' => false, 'motivo' => 'Pedido deve estar entregue para ser avaliado'];
        }

        if (self::pedidoJaAvaliado($pedidoId)) {
            return ['pode' => false, 'motivo' => 'Pedido já foi avaliado'];
        }

        return ['pode' => true, 'pedido' => $pedido];
    }

    /**
     * Método para contar avaliações por empresa
     */
    public static function contarAvaliacoesEmpresa($empresaId)
    {
        return self::where('empresa_id', $empresaId)->count();
    }
}