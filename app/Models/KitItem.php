<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KitItem extends Model
{
    use HasFactory;

    protected $table = 'kit_itens';

    protected $fillable = [
        'kit_id',
        'produto_id',
        'quantidade',
    ];

    public function kit()
    {
        return $this->belongsTo(Kit::class, 'kit_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
