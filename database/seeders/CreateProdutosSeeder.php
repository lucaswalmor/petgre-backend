<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\UnidadeMedida;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProdutosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Cria 30 produtos reais de petshop para a empresa 1
     */
    public function run(): void
    {
        $empresaId = 1; // ID da empresa
        $timestamp = Carbon::now();

        // Buscar ou criar categorias
        $categorias = $this->getOrCreateCategorias();
        
        // Buscar unidade de medida padrão (Unidade)
        $unidade = UnidadeMedida::where('sigla', 'UN')->first();
        if (!$unidade) {
            $unidade = UnidadeMedida::first();
        }

        if (!$unidade) {
            $this->command->error('Nenhuma unidade de medida encontrada!');
            return;
        }

        // Array com 30 produtos reais de petshop
        $produtos = [
            // Rações
            [
                'nome' => 'Ração Premium Cães Adultos 15kg',
                'descricao' => 'Ração premium para cães adultos de todas as raças. Balanceada e nutritiva.',
                'preco' => 149.90,
                'categoria' => 'Rações',
                'tipo' => 'produto',
                'destaque' => true,
            ],
            [
                'nome' => 'Ração Premium Cães Filhotes 10kg',
                'descricao' => 'Ração especial para cães filhotes até 12 meses. Rico em proteínas.',
                'preco' => 129.90,
                'categoria' => 'Rações',
                'tipo' => 'produto',
                'destaque' => true,
            ],
            [
                'nome' => 'Ração Gatos Adultos Premium 10kg',
                'descricao' => 'Ração premium para gatos adultos castrados ou não.',
                'preco' => 159.90,
                'categoria' => 'Rações',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            [
                'nome' => 'Ração Gatos Filhotes 7kg',
                'descricao' => 'Ração especial para gatos filhotes até 12 meses.',
                'preco' => 119.90,
                'categoria' => 'Rações',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            // Petiscos
            [
                'nome' => 'Petisco Biscrok Cães 500g',
                'descricao' => 'Biscoitos crocantes para cães de todas as raças.',
                'preco' => 24.90,
                'categoria' => 'Petiscos',
                'tipo' => 'produto',
                'destaque' => true,
            ],
            [
                'nome' => 'Ossinho Palito Sabor Frango 1kg',
                'descricao' => 'Ossinhos palito para cães. Auxilia na limpeza dentária.',
                'preco' => 29.90,
                'categoria' => 'Petiscos',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            [
                'nome' => 'Petisco Temptations Gatos 85g',
                'descricao' => 'Petisco crocante para gatos. Sabor frango.',
                'preco' => 12.90,
                'categoria' => 'Petiscos',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            [
                'nome' => 'Snack Dental Cães 200g',
                'descricao' => 'Snack dental para cães. Reduz tártaro e mau hálito.',
                'preco' => 34.90,
                'categoria' => 'Petiscos',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            // Brinquedos
            [
                'nome' => 'Bola de Borracha Resistente Grande',
                'descricao' => 'Bola de borracha super resistente para cães de porte médio e grande.',
                'preco' => 39.90,
                'categoria' => 'Brinquedos',
                'tipo' => 'produto',
                'destaque' => true,
            ],
            [
                'nome' => 'Osso de Nylon Sabor Bacon',
                'descricao' => 'Osso de nylon para cães roedores. Sabor bacon.',
                'preco' => 45.90,
                'categoria' => 'Brinquedos',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            [
                'nome' => 'Arranhador para Gatos Pequeno',
                'descricao' => 'Arranhador de papelão com catnip. 40cm de altura.',
                'preco' => 59.90,
                'categoria' => 'Brinquedos',
                'tipo' => 'produto',
                'destaque' => true,
            ],
            [
                'nome' => 'Ratinho de Pelúcia com Catnip',
                'descricao' => 'Brinquedo de pelúcia em formato de rato com catnip.',
                'preco' => 19.90,
                'categoria' => 'Brinquedos',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            // Higiene e Limpeza
            [
                'nome' => 'Tapete Higiênico Cães 30 unidades',
                'descricao' => 'Tapete higiênico super absorvente para cães. 80x60cm.',
                'preco' => 49.90,
                'categoria' => 'Higiene e Limpeza',
                'tipo' => 'produto',
                'destaque' => true,
            ],
            [
                'nome' => 'Areia Sanitária Gatos 4kg',
                'descricao' => 'Areia sanitária aglomerante para gatos. Elimina odores.',
                'preco' => 22.90,
                'categoria' => 'Higiene e Limpeza',
                'tipo' => 'produto',
                'destaque' => true,
            ],
            [
                'nome' => 'Shampoo Cães e Gatos 500ml',
                'descricao' => 'Shampoo neutro para cães e gatos. Ph balanceado.',
                'preco' => 34.90,
                'categoria' => 'Higiene e Limpeza',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            [
                'nome' => 'Coleira Antipulgas e Carrapatos',
                'descricao' => 'Coleira repelente de pulgas e carrapatos. Dura 4 meses.',
                'preco' => 69.90,
                'categoria' => 'Higiene e Limpeza',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            // Acessórios
            [
                'nome' => 'Coleira de Nylon Ajustável M',
                'descricao' => 'Coleira de nylon resistente, ajustável. Tamanho M.',
                'preco' => 24.90,
                'categoria' => 'Acessórios',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            [
                'nome' => 'Guia Retrátil 5 metros',
                'descricao' => 'Guia retrátil para passeio. Suporta até 20kg.',
                'preco' => 54.90,
                'categoria' => 'Acessórios',
                'tipo' => 'produto',
                'destaque' => true,
            ],
            [
                'nome' => 'Cama Pet Soft Tam. M',
                'descricao' => 'Cama super macia para cães e gatos. Tamanho M.',
                'preco' => 119.90,
                'categoria' => 'Acessórios',
                'tipo' => 'produto',
                'destaque' => true,
            ],
            [
                'nome' => 'Comedouro Duplo Inox',
                'descricao' => 'Comedouro duplo em aço inox antiderrapante.',
                'preco' => 44.90,
                'categoria' => 'Acessórios',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            // Medicamentos
            [
                'nome' => 'Vermífugo Cães até 10kg',
                'descricao' => 'Vermífugo completo para cães até 10kg. 4 comprimidos.',
                'preco' => 39.90,
                'categoria' => 'Medicamentos',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            [
                'nome' => 'Antipulgas Frontline Gatos',
                'descricao' => 'Antipulgas em pipeta para gatos. Proteção de 30 dias.',
                'preco' => 59.90,
                'categoria' => 'Medicamentos',
                'tipo' => 'produto',
                'destaque' => true,
            ],
            [
                'nome' => 'Suplemento Vitamínico Pet 120ml',
                'descricao' => 'Suplemento vitamínico líquido para cães e gatos.',
                'preco' => 45.90,
                'categoria' => 'Medicamentos',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            // Serviços
            [
                'nome' => 'Banho e Tosa Completo Cães P',
                'descricao' => 'Serviço de banho e tosa completo para cães de pequeno porte.',
                'preco' => 59.90,
                'categoria' => 'Serviços',
                'tipo' => 'servico',
                'destaque' => true,
            ],
            [
                'nome' => 'Banho e Tosa Completo Cães M',
                'descricao' => 'Serviço de banho e tosa completo para cães de médio porte.',
                'preco' => 79.90,
                'categoria' => 'Serviços',
                'tipo' => 'servico',
                'destaque' => true,
            ],
            [
                'nome' => 'Banho e Tosa Completo Cães G',
                'descricao' => 'Serviço de banho e tosa completo para cães de grande porte.',
                'preco' => 99.90,
                'categoria' => 'Serviços',
                'tipo' => 'servico',
                'destaque' => false,
            ],
            [
                'nome' => 'Banho Gatos',
                'descricao' => 'Serviço de banho especializado para gatos.',
                'preco' => 69.90,
                'categoria' => 'Serviços',
                'tipo' => 'servico',
                'destaque' => false,
            ],
            [
                'nome' => 'Tosa Higiênica',
                'descricao' => 'Serviço de tosa higiênica para cães.',
                'preco' => 39.90,
                'categoria' => 'Serviços',
                'tipo' => 'servico',
                'destaque' => false,
            ],
            // Outros
            [
                'nome' => 'Caixa de Transporte Nº2',
                'descricao' => 'Caixa de transporte plástica com porta de metal.',
                'preco' => 89.90,
                'categoria' => 'Outros',
                'tipo' => 'produto',
                'destaque' => false,
            ],
            [
                'nome' => 'Bebedouro Fonte Elétrica',
                'descricao' => 'Fonte bebedouro elétrica para cães e gatos. 110V.',
                'preco' => 149.90,
                'categoria' => 'Outros',
                'tipo' => 'produto',
                'destaque' => true,
            ],
            [
                'nome' => 'Kit Higiene Dental Cães',
                'descricao' => 'Kit com escova, pasta e dedeira para higiene dental.',
                'preco' => 29.90,
                'categoria' => 'Outros',
                'tipo' => 'produto',
                'destaque' => false,
            ],
        ];

        $this->command->info('🧪 Criando 30 produtos de petshop para empresa ID: ' . $empresaId);
        $this->command->info('');

        $produtosCriados = 0;

        foreach ($produtos as $index => $produtoData) {
            DB::beginTransaction();
            try {
                // Buscar categoria
                $categoria = $categorias[$produtoData['categoria']] ?? $categorias['Outros'];

                // Criar slug único
                $slug = Str::slug($produtoData['nome']);
                $slugOriginal = $slug;
                $contador = 1;
                
                while (DB::table('produtos')->where('slug', $slug)->where('empresa_id', $empresaId)->exists()) {
                    $slug = $slugOriginal . '-' . $contador;
                    $contador++;
                }

                // Inserir produto
                $produtoId = DB::table('produtos')->insertGetId([
                    'empresa_id' => $empresaId,
                    'nome' => $produtoData['nome'],
                    'slug' => $slug,
                    'descricao' => $produtoData['descricao'],
                    'preco' => $produtoData['preco'],
                    'categoria_id' => $categoria,
                    'unidade_medida_id' => $unidade->id,
                    'tipo' => $produtoData['tipo'],
                    'destaque' => $produtoData['destaque'],
                    'ativo' => true,
                    'estoque' => $produtoData['tipo'] === 'produto' ? rand(10, 100) : null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                DB::commit();
                $produtosCriados++;

                $tipoLabel = $produtoData['tipo'] === 'servico' ? '🛁' : '📦';
                $this->command->info("{$tipoLabel} {$produtoData['nome']} - R$ {$produtoData['preco']} (ID: {$produtoId})");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->error("❌ Erro ao criar produto '{$produtoData['nome']}': {$e->getMessage()}");
                continue;
            }
        }

        $this->command->info('');
        $this->command->info('✅ CreateProdutosSeeder executado: ' . $produtosCriados . ' produtos criados!');
        $this->command->info('');
        $this->command->info('📋 RESUMO:');
        $this->command->info('   - Produtos: ' . count(array_filter($produtos, fn($p) => $p['tipo'] === 'produto')));
        $this->command->info('   - Serviços: ' . count(array_filter($produtos, fn($p) => $p['tipo'] === 'servico')));
        $this->command->info('   - Destaques: ' . count(array_filter($produtos, fn($p) => $p['destaque'])));
        $this->command->info('');
        $this->command->info('💡 Execute: php artisan db:seed --class=CreateProdutosSeeder');
    }

    /**
     * Buscar ou criar categorias necessárias
     */
    private function getOrCreateCategorias(): array
    {
        $nomesCategorias = ['Rações', 'Petiscos', 'Brinquedos', 'Higiene e Limpeza', 'Acessórios', 'Medicamentos', 'Serviços', 'Outros'];
        $categorias = [];

        foreach ($nomesCategorias as $nome) {
            $categoria = Categoria::where('nome', $nome)->first();
            
            if (!$categoria) {
                $categoria = Categoria::create([
                    'nome' => $nome,
                    'slug' => Str::slug($nome),
                    'ativo' => true,
                ]);
                $this->command->info("📁 Categoria criada: {$nome}");
            }

            $categorias[$nome] = $categoria->id;
        }

        return $categorias;
    }
}
