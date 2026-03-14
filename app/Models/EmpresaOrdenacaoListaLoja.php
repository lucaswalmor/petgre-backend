<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaOrdenacaoListaLoja extends Model
{
    use HasFactory;

    protected $table = 'empresa_ordenacao_lista_loja';

    protected $fillable = [
        'empresa_id',
        'secao',
        'ordem',
        'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean'
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Obter ordenação padrão para uma empresa
     */
    public static function getOrdenacaoPadrao($empresaId)
    {
        $ordenacao = self::where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->pluck('secao')
            ->toArray();

        // Se não tiver configuração, retorna ordem padrão
        if (empty($ordenacao)) {
            return ['produtos', 'kits'];
        }

        return $ordenacao;
    }

    /**
     * Salvar ou atualizar ordenação
     */
    public static function salvarOrdenacao($empresaId, array $secoes)
    {
        // Desativa todas as entradas existentes
        self::where('empresa_id', $empresaId)->update(['ativo' => false]);

        // Cria novas entradas com a ordem
        foreach ($secoes as $index => $secao) {
            self::updateOrCreate(
                [
                    'empresa_id' => $empresaId,
                    'secao' => $secao
                ],
                [
                    'ordem' => $index + 1,
                    'ativo' => true
                ]
            );
        }

        return true;
    }
}