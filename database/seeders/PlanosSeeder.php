<?php

namespace Database\Seeders;

use App\Models\Plano;
use Illuminate\Database\Seeder;

class PlanosSeeder extends Seeder
{
    public function run(): void
    {
        if (Plano::withoutTrashed()->where('slug', 'plano-petgre')->exists()) {
            return;
        }

        Plano::create([
            'nome' => 'Plano PetGre',
            'slug' => 'plano-petgre',
            'valor' => 39.90,
            'ativo' => true,
        ]);
    }
}
