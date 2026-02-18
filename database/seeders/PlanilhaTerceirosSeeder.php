<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanilhaTerceirosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\PlanilhaTerceiros::create([
            'nome' => 'upgestao'
        ]);

        \App\Models\PlanilhaTerceiros::create([
            'nome' => 'bling'
        ]);

        \App\Models\PlanilhaTerceiros::create([
            'nome' => 'tiny'
        ]);
    }
}
