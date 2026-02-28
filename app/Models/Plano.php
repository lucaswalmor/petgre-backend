<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plano extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'planos';

    protected $fillable = ['nome', 'slug', 'valor', 'ativo'];

    protected $casts = [
        'valor' => 'decimal:2',
        'ativo' => 'boolean',
    ];

}
