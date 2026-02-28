<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioClientesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Cria usuários do tipo cliente (tipo_cadastro = 1) para testes
     */
    public function run(): void
    {
        $timestamp = now();

        // Lista de clientes para criar
        $clientes = [
            [
                'nome' => 'Lucas Teste Pagamento',
                'email' => 'lucaswsb5@gmail.com',
                'telefone' => '(34) 99202-1394',
                'password' => '32329585',
                'ativo' => true,
                'primeiro_login' => false,
                'tipo_cadastro' => 1, // Cliente
                'is_master' => false,
                'enderecos' => [
                    [
                        'rua' => 'Rua izídio antonio da silva',
                        'numero' => '500',
                        'complemento' => null,
                        'bairro' => 'Presidente Roosevelt',
                        'cidade' => 'Uberlândia',
                        'estado' => 'MG',  
                        'cep' => '38401-132',
                        'endereco_padrao' => true,
                    ]
                ]
            ],
        ];

        $clientesCriados = 0;

        foreach ($clientes as $clienteData) {
            // Verifica se o email já existe
            $usuarioExistente = DB::table('usuarios')->where('email', $clienteData['email'])->first();

            if ($usuarioExistente) {
                $this->command->info("Cliente {$clienteData['email']} já existe, pulando...");
                continue;
            }

            // Separa os endereços dos dados do usuário
            $enderecos = $clienteData['enderecos'];
            unset($clienteData['enderecos']);

            // Cria o usuário
            $usuarioId = DB::table('usuarios')->insertGetId(array_merge($clienteData, [
                'password' => Hash::make($clienteData['password']),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]));

            // Cria os endereços
            foreach ($enderecos as $endereco) {
                DB::table('usuarios_enderecos')->insert(array_merge($endereco, [
                    'usuario_id' => $usuarioId,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]));
            }

            $clientesCriados++;
            $this->command->info("Cliente criado: {$clienteData['nome']} ({$clienteData['email']})");
        }

        $this->command->info("✓ UsuarioClientesSeeder executado: {$clientesCriados} cliente(s) criado(s)!");
    }
}