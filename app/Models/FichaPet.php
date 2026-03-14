<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FichaPet extends Model
{
    use HasFactory;

    protected $table = 'ficha_pets';

    protected $fillable = [
        'usuario_id',
        'empresa_id',
        'nome',
        'raca',
        'porte',
        'tamanho_pelagem',
        'idade',
        'unidade_idade',
        'comportamento',
        'alergias',
        'foto_url'
    ];

    protected $casts = [
        'idade' => 'integer'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Retorna idade formatada
     */
    public function getIdadeFormatadaAttribute()
    {
        if (!$this->idade) return null;
        
        $unidade = $this->unidade_idade === 'meses' ? 'meses' : 'anos';
        return "{$this->idade} {$unidade}";
    }

    /**
     * Retorna porte formatado com descrição
     */
    public function getPorteFormatadoAttribute()
    {
        $descricoes = [
            'pequeno' => 'Pequeno (até 10kg)',
            'medio' => 'Médio (10-25kg)',
            'grande' => 'Grande (25kg+)'
        ];

        return $descricoes[$this->porte] ?? $this->porte;
    }

    /**
     * Retorna pelagem formatada
     */
    public function getPelagemFormatadaAttribute()
    {
        $descricoes = [
            'curta' => 'Curta',
            'media' => 'Média',
            'longa' => 'Longa'
        ];

        return $descricoes[$this->tamanho_pelagem] ?? $this->tamanho_pelagem;
    }
}