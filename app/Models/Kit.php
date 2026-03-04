<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kits';

    protected $fillable = [
        'empresa_id',
        'nome',
        'descricao',
        'imagem',
        'preco',
        'ativo',
    ];

    protected $casts = [
        'preco' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function itens()
    {
        return $this->hasMany(KitItem::class, 'kit_id');
    }

    public function produtos()
    {
        return $this->belongsToMany(Produto::class, 'kit_itens', 'kit_id', 'produto_id')
            ->withPivot('quantidade')
            ->withTimestamps();
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
