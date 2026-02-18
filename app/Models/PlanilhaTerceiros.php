<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanilhaTerceiros extends Model
{
    protected $table = 'planilhas_terceiros';
    
    protected $fillable = [
        'nome',
    ];
}
