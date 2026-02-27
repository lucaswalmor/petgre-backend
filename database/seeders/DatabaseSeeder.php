<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Obrigatório: seeders do sistema (menus, permissões, bairros, planos, etc.)
        $this->call(SistemaSeeder::class);

        // Usuário de teste apenas em local (ou se CREATE_TEST_USER=true no .env)
        if (app()->environment('local') || env('CREATE_TEST_USER', false)) {
            if (!User::where('email', 'test@example.com')->exists()) {
                $user = User::create([
                    'nome' => 'Test User',
                    'email' => 'test@example.com',
                    'password' => Hash::make('password'),
                    'telefone' => '(11) 99999-9999',
                    'ativo' => true,
                    'is_master' => true,
                    'tipo_cadastro' => 0,
                ]);
                $user->permissoes()->sync(ids: [1]);
            }
        }
    }
}
