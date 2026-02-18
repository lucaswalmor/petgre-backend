<?php

namespace App\Services\Importacao;

use Illuminate\Http\UploadedFile;
use App\Models\Produto;
use App\Models\Categorias;
use App\Models\UnidadeMedida;
use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;
use Illuminate\Support\Facades\Log;

class PetgreImportacaoService implements PlanilhaImportacaoInterface
{
    /**
     * Processa a importação da planilha Petgre
     *
     * @param UploadedFile $arquivo
     * @return array
     */
    public function importar(UploadedFile $arquivo): array
    {
        // Debug: informações do arquivo
        Log::info('Processando arquivo Excel:', [
            'nome' => $arquivo->getClientOriginalName(),
            'extensao' => $arquivo->getClientOriginalExtension(),
            'mime_type' => $arquivo->getMimeType(),
            'caminho' => $arquivo->getPathname()
        ]);

        $usuarioAutenticado = Auth::user();
        $empresasIds = $usuarioAutenticado->empresas->pluck('id')->toArray();

        $total = 0;
        $importados = 0;
        $erros = [];
        $linhasComErro = [];
        $dadosOriginais = [];

        DB::beginTransaction();
        try {
            $this->processarExcel($arquivo, $dadosOriginais, $linhasComErro, $total, $importados, $empresasIds);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // Gerar planilha de erros se houver erros
        $planilhaErrosUrl = null;
        if (!empty($linhasComErro)) {
            $empresaId = $empresasIds[0] ?? null;
            if ($empresaId) {
                $planilhaErrosUrl = $this->gerarPlanilhaErros($dadosOriginais, $linhasComErro, $empresaId);
            }
        }

        // Converter erros para formato compatível com resposta anterior
        foreach ($linhasComErro as $erro) {
            $erros[] = [
                'linha' => $erro['linha'],
                'mensagem' => $erro['motivo']
            ];
        }

        return [
            'total' => $total,
            'importados' => $importados,
            'erros' => count($linhasComErro),
            'planilha_erros_url' => $planilhaErrosUrl,
            'linhas_com_erro' => $linhasComErro
        ];
    }

    /**
     * Valida se a estrutura da planilha é compatível com o formato Petgre
     * Agora aceita planilhas parciais (com apenas alguns campos)
     *
     * @param array $cabecalho
     * @return bool
     */
    public function validarEstrutura(array $cabecalho): bool
    {
        Log::info('Validando estrutura da planilha:', [
            'cabecalho_recebido' => $cabecalho,
            'quantidade_colunas' => count($cabecalho)
        ]);

        // Verificar se tem pelo menos os campos obrigatórios
        $camposObrigatorios = ['Nome*', 'Preço*'];
        $cabecalhoNormalizado = array_map(function($coluna) {
            return trim(strtolower(preg_replace('/["\']/', '', $coluna)));
        }, $cabecalho);

        $camposObrigatoriosNormalizados = array_map('strtolower', $camposObrigatorios);

        foreach ($camposObrigatoriosNormalizados as $campoObrigatorio) {
            if (!in_array($campoObrigatorio, $cabecalhoNormalizado)) {
                Log::warning('Campo obrigatório não encontrado:', [
                    'campo_procurado' => $campoObrigatorio,
                    'cabecalho_normalizado' => $cabecalhoNormalizado
                ]);
                return false;
            }
        }

        // Verificar se não há campos duplicados ou vazios
        $cabecalhoLimpo = array_filter($cabecalho, function($coluna) {
            return !empty(trim($coluna));
        });

        if (count($cabecalhoLimpo) !== count($cabecalho)) {
            Log::warning('Cabeçalho contém colunas vazias');
            return false;
        }

        if (count($cabecalhoLimpo) !== count(array_unique($cabecalhoLimpo))) {
            Log::warning('Cabeçalho contém colunas duplicadas');
            return false;
        }

        Log::info('Estrutura da planilha validada com sucesso - campos obrigatórios presentes');
        return true;
    }

    /**
     * Retorna o cabeçalho esperado da planilha Petgre
     *
     * @return array
     */
    public function getCabecalhoEsperado(): array
    {
        return [
            'Nome*',
            'Descrição',
            'Categoria',
            'Unidade de Medida',
            'Preço*',
            'Estoque',
            'Marca',
            'SKU',
            'Preço de Custo',
            'Estoque Mínimo',
            'Peso (kg)',
            'Altura (cm)',
            'Largura (cm)',
            'Comprimento (cm)',
            'Ordem',
            'Preço Promocional',
            'Promoção Até (YYYY-MM-DD)',
            'Vende a Granel (S/N)',
            'Tipo (produto/serviço)',
            'Ativo (S/N)',
            'Destaque (S/N)'
        ];
    }

    /**
     * Processa uma linha da planilha e cria o produto
     * Agora suporta planilhas parciais (com menos colunas)
     *
     * @param array $dados
     * @param int $linhaIndex
     * @param array $empresasIds
     * @return Produto|null
     */
    private function processarLinha(array $dados, int $linhaIndex, array $empresasIds): ?Produto
    {
        // Usar apenas os campos que existem nos dados
        $numColunas = count($dados);

        // Mapear baseado no número de colunas disponíveis
        $mapeamento = [];

        // Sempre assume que as primeiras colunas seguem a ordem padrão até onde houver dados
        $mapeamentoCampos = [
            0 => 'nome',
            1 => 'descricao',
            2 => 'categoria_nome',
            3 => 'unidade_medida_nome',
            4 => 'preco',
            5 => 'estoque',
            6 => 'marca',
            7 => 'sku',
            8 => 'preco_custo',
            9 => 'estoque_minimo',
            10 => 'peso',
            11 => 'altura',
            12 => 'largura',
            13 => 'comprimento',
            14 => 'ordem',
            15 => 'preco_promocional',
            16 => 'promocao_ate',
            17 => 'vende_granel',
            18 => 'tipo',
            19 => 'ativo',
            20 => 'destaque'
        ];

        // Mapear apenas os campos que existem nos dados
        for ($i = 0; $i < $numColunas; $i++) {
            if (isset($mapeamentoCampos[$i])) {
                $mapeamento[$mapeamentoCampos[$i]] = $dados[$i] ?? null;
            }
        }

        // Validações obrigatórias
        if (empty($mapeamento['nome'])) {
            throw new \Exception('Campo "Nome" é obrigatório');
        }

        if (empty($mapeamento['preco']) || !is_numeric($mapeamento['preco'])) {
            throw new \Exception('Campo "Preço" é obrigatório e deve ser numérico');
        }

        // Usar primeira empresa do usuário (normalmente haverá apenas uma)
        $empresaId = $empresasIds[0] ?? null;
        if (!$empresaId) {
            throw new \Exception('Usuário não possui empresa associada');
        }

        // Processar categoria
        $categoriaId = null;
        if (!empty($mapeamento['categoria_nome'])) {
            $categoria = Categorias::where('nome', 'like', $mapeamento['categoria_nome'])->first();
            if (!$categoria) {
                throw new \Exception("Categoria '{$mapeamento['categoria_nome']}' não encontrada");
            }
            $categoriaId = $categoria->id;
        }

        // Processar unidade de medida
        $unidadeMedidaId = null;
        if (!empty($mapeamento['unidade_medida_nome'])) {
            $unidade = UnidadeMedida::where('nome', 'like', $mapeamento['unidade_medida_nome'])
                ->orWhere('sigla', 'like', $mapeamento['unidade_medida_nome'])
                ->first();
            if (!$unidade) {
                throw new \Exception("Unidade de medida '{$mapeamento['unidade_medida_nome']}' não encontrada");
            }
            $unidadeMedidaId = $unidade->id;
        }

        // Processar campos booleanos
        $vendeGranel = $this->converterParaBooleano($mapeamento['vende_granel']);
        $ativo = $this->converterParaBooleano($mapeamento['ativo'], true); // Default true
        $destaque = $this->converterParaBooleano($mapeamento['destaque'], false); // Default false

        // Processar tipo
        $tipo = strtolower($mapeamento['tipo'] ?? 'produto');
        if (!in_array($tipo, ['produto', 'servico'])) {
            $tipo = 'produto';
        }

        // Ajustar estoque para serviços
        $estoque = $tipo === 'servico' ? 0 : ($mapeamento['estoque'] ?? 0);

        // Processar datas
        $promocaoAte = null;
        if (!empty($mapeamento['promocao_ate'])) {
            try {
                $promocaoAte = date('Y-m-d', strtotime($mapeamento['promocao_ate']));
            } catch (\Exception $e) {
                // Ignorar data inválida
            }
        }

        // Criar produto
        $produto = new Produto([
            'empresa_id' => $empresaId,
            'categoria_id' => $categoriaId,
            'unidade_medida_id' => $unidadeMedidaId,
            'tipo' => $tipo,
            'nome' => trim($mapeamento['nome']),
            'slug' => Str::slug($mapeamento['nome']),
            'descricao' => $mapeamento['descricao'] ? trim($mapeamento['descricao']) : null,
            'preco' => (float) $mapeamento['preco'],
            'estoque' => (float) ($estoque ?? 0),
            'destaque' => $destaque,
            'ativo' => $ativo,
            'marca' => $mapeamento['marca'] ? trim($mapeamento['marca']) : null,
            'sku' => $mapeamento['sku'] ? trim($mapeamento['sku']) : null,
            'preco_custo' => $mapeamento['preco_custo'] ? (float) $mapeamento['preco_custo'] : null,
            'estoque_minimo' => $mapeamento['estoque_minimo'] ? (float) $mapeamento['estoque_minimo'] : 0,
            'peso' => $mapeamento['peso'] ? (float) $mapeamento['peso'] : null,
            'altura' => $mapeamento['altura'] ? (float) $mapeamento['altura'] : null,
            'largura' => $mapeamento['largura'] ? (float) $mapeamento['largura'] : null,
            'comprimento' => $mapeamento['comprimento'] ? (float) $mapeamento['comprimento'] : null,
            'ordem' => $mapeamento['ordem'] ? (int) $mapeamento['ordem'] : 0,
            'preco_promocional' => $mapeamento['preco_promocional'] ? (float) $mapeamento['preco_promocional'] : null,
            'promocao_ate' => $promocaoAte,
            'tem_promocao' => !empty($mapeamento['preco_promocional']),
            'vende_granel' => $vendeGranel,
        ]);

        return $produto;
    }

    /**
     * Valida uma linha da planilha e retorna erros detalhados
     *
     * @param array $dados
     * @param int $linhaIndex
     * @return array
     */
    private function validarLinha(array $dados, int $linhaIndex): array
    {
        $erros = [];
        $numColunas = count($dados);

        // Mapeamento dinâmico baseado no número de colunas disponíveis
        $mapeamentoCampos = [
            0 => 'Nome*',
            1 => 'Descrição',
            2 => 'Categoria',
            3 => 'Unidade de Medida',
            4 => 'Preço*',
            5 => 'Estoque',
            6 => 'Marca',
            7 => 'SKU',
            8 => 'Preço de Custo',
            9 => 'Estoque Mínimo',
            10 => 'Peso (kg)',
            11 => 'Altura (cm)',
            12 => 'Largura (cm)',
            13 => 'Comprimento (cm)',
            14 => 'Ordem',
            15 => 'Preço Promocional',
            16 => 'Promoção Até (YYYY-MM-DD)',
            17 => 'Vende a Granel (S/N)',
            18 => 'Tipo (produto/serviço)',
            19 => 'Ativo (S/N)',
            20 => 'Destaque (S/N)'
        ];

        // Filtrar apenas os campos que existem na planilha
        $colunasDisponiveis = [];
        for ($i = 0; $i < $numColunas; $i++) {
            if (isset($mapeamentoCampos[$i])) {
                $colunasDisponiveis[$i] = $mapeamentoCampos[$i];
            }
        }

        // 1. Validação de campos obrigatórios
        // Nome (sempre esperado na primeira coluna se existir)
        if (isset($dados[0]) && empty(trim($dados[0] ?? ''))) {
            $nomeColuna = $colunasDisponiveis[0] ?? 'Nome*';
            $erros[] = [
                'coluna' => $nomeColuna,
                'motivo' => 'Campo obrigatório não preenchido',
                'como_corrigir' => 'Digite o nome do produto (máximo 255 caracteres)'
            ];
        }

        // Preço (procurar pela coluna de preço)
        $indicePreco = array_search('Preço*', $colunasDisponiveis);
        if ($indicePreco !== false) {
            $preco = $dados[$indicePreco] ?? null;
            if (empty($preco) || !is_numeric($preco)) {
                $erros[] = [
                    'coluna' => $colunasDisponiveis[$indicePreco],
                    'motivo' => 'Campo obrigatório deve ser numérico',
                    'como_corrigir' => 'Digite um preço válido (ex: 29.90). Use ponto como separador decimal'
                ];
            }
        }

        // 2. Validações de formato para campos que existem
        foreach ($colunasDisponiveis as $indice => $nomeColuna) {
            $valor = $dados[$indice] ?? null;

            if (empty($valor)) continue;

            switch ($nomeColuna) {
                case 'Preço*':
                case 'Preço de Custo':
                case 'Preço Promocional':
                    if (!is_numeric($valor)) {
                        $erros[] = [
                            'coluna' => $nomeColuna,
                            'motivo' => 'Formato de preço inválido',
                            'como_corrigir' => 'Use apenas números e ponto decimal (ex: 29.90)'
                        ];
                    }
                    break;

                case 'Estoque':
                case 'Estoque Mínimo':
                case 'Peso (kg)':
                case 'Altura (cm)':
                case 'Largura (cm)':
                case 'Comprimento (cm)':
                case 'Ordem':
                    if (!is_numeric($valor) && $valor !== '0' && $valor !== '') {
                        $erros[] = [
                            'coluna' => $nomeColuna,
                            'motivo' => 'Campo deve ser numérico',
                            'como_corrigir' => 'Digite apenas números (ex: 10 ou 10.5)'
                        ];
                    }
                    break;

                case 'Promoção Até (YYYY-MM-DD)':
                    $dataValida = $this->validarData($valor);
                    if (!$dataValida) {
                        $erros[] = [
                            'coluna' => $nomeColuna,
                            'motivo' => 'Formato de data inválido',
                            'como_corrigir' => 'Use o formato YYYY-MM-DD (ex: 2024-12-31)'
                        ];
                    }
                    break;

                case 'Categoria':
                    $categoriaExiste = Categorias::where('nome', 'like', trim($valor))->exists();
                    if (!$categoriaExiste) {
                        $erros[] = [
                            'coluna' => $nomeColuna,
                            'motivo' => 'Categoria não encontrada',
                            'como_corrigir' => 'Use uma categoria existente (ex: Rações, Brinquedos, Higiene)'
                        ];
                    }
                    break;

                case 'Unidade de Medida':
                    $unidadeExiste = UnidadeMedida::where('nome', 'like', trim($valor))
                        ->orWhere('sigla', 'like', trim($valor))
                        ->exists();
                    if (!$unidadeExiste) {
                        $erros[] = [
                            'coluna' => $nomeColuna,
                            'motivo' => 'Unidade de medida não encontrada',
                            'como_corrigir' => 'Use uma unidade existente (ex: Unidade, Pacote, Quilo, Litro)'
                        ];
                    }
                    break;

                case 'Vende a Granel (S/N)':
                case 'Ativo (S/N)':
                case 'Destaque (S/N)':
                    $valorNormalizado = strtolower(trim($valor));
                    if (!in_array($valorNormalizado, ['s', 'n', 'sim', 'não', 'nao', ''])) {
                        $erros[] = [
                            'coluna' => $nomeColuna,
                            'motivo' => 'Valor inválido para campo S/N',
                            'como_corrigir' => 'Digite apenas S (Sim) ou N (Não), ou deixe vazio'
                        ];
                    }
                    break;

                case 'Tipo (produto/serviço)':
                    $valorNormalizado = strtolower(trim($valor));
                    if (!in_array($valorNormalizado, ['produto', 'serviço', 'servico', ''])) {
                        $erros[] = [
                            'coluna' => $nomeColuna,
                            'motivo' => 'Tipo inválido',
                            'como_corrigir' => 'Digite apenas "produto" ou "serviço", ou deixe vazio'
                        ];
                    }
                    break;

                case 'Nome*':
                    if (strlen(trim($valor)) > 255) {
                        $erros[] = [
                            'coluna' => $nomeColuna,
                            'motivo' => 'Nome muito longo',
                            'como_corrigir' => 'Nome deve ter no máximo 255 caracteres'
                        ];
                    }
                    break;

                case 'Descrição':
                    if (strlen(trim($valor)) > 1000) {
                        $erros[] = [
                            'coluna' => $nomeColuna,
                            'motivo' => 'Descrição muito longa',
                            'como_corrigir' => 'Descrição deve ter no máximo 1000 caracteres'
                        ];
                    }
                    break;
            }
        }

        return $erros;
    }

    /**
     * Valida formato de data
     *
     * @param string $data
     * @return bool
     */
    private function validarData(string $data): bool
    {
        $data = trim($data);
        if (empty($data)) return true; // Data vazia é válida

        // Tentar diferentes formatos
        $formatos = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
        foreach ($formatos as $formato) {
            $dateTime = \DateTime::createFromFormat($formato, $data);
            if ($dateTime && $dateTime->format($formato) === $data) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gera planilha de erros com duas abas
     *
     * @param array $dadosOriginais
     * @param array $linhasComErro
     * @param int $empresaId
     * @return string|null
     */
    private function gerarPlanilhaErros(array $dadosOriginais, array $linhasComErro, int $empresaId): ?string
    {
        try {
            // getCabecalhoEsperado() é a fonte única de verdade para os nomes das colunas
            // dadosOriginais[0] = linha 2 da planilha (linhaIndex=2), [1] = linha 3, etc.
            // portanto: linha N da planilha → dadosOriginais[N - 2]
            $cabecalho = $this->getCabecalhoEsperado();

            $linhasErroIndices = array_unique(array_column($linhasComErro, 'linha'));
            $dadosComErro = collect();
            foreach ($linhasErroIndices as $linhaErro) {
                $indice = $linhaErro - 2;
                if (isset($dadosOriginais[$indice])) {
                    // array_combine garante que as chaves são os nomes corretos das colunas
                    $dadosComErro->push(array_combine($cabecalho, $dadosOriginais[$indice]));
                }
            }

            $detalhesErros = collect();
            foreach ($linhasComErro as $erro) {
                $detalhesErros->push([
                    'Linha'          => $erro['linha'],
                    'Coluna'         => $erro['coluna'],
                    'Motivo do erro' => $erro['motivo'],
                    'Como corrigir'  => $erro['como_corrigir'],
                ]);
            }

            $sheets = new SheetCollection([
                'Dados com erro'     => $dadosComErro,
                'Detalhes dos erros' => $detalhesErros,
            ]);

            $tempFile = tempnam(sys_get_temp_dir(), 'erros_') . '.xlsx';
            (new FastExcel($sheets))->export($tempFile);

            $path = "planilhas/empresa/{$empresaId}/importacao_produto_empresa_{$empresaId}.xlsx";

            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            Storage::disk('local')->put($path, file_get_contents($tempFile));
            unlink($tempFile);

            return "/api/produtos/importar/erros/download";

        } catch (\Exception $e) {
            Log::error('Erro ao gerar planilha de erros: ' . $e->getMessage());
            return null;
        }
    }

    private function processarExcel($arquivo, &$dadosOriginais, &$linhasComErro, &$total, &$importados, $empresasIds)
    {
        try {
            // FastExcel pula o cabeçalho automaticamente e retorna arrays associativos
            $linhas = (new FastExcel)->import($arquivo->getPathname());

            $linhaIndex = 1;

            foreach ($linhas as $linha) {
                $linhaIndex++;

                // Guardar como array indexado (para validação e para gerarPlanilhaErros)
                $dados = array_values($linha);
                $dadosOriginais[] = $dados;

                $total++;

                if (empty(array_filter($dados))) {
                    continue;
                }

                try {
                    $errosValidacao = $this->validarLinha($dados, $linhaIndex);
                    if (!empty($errosValidacao)) {
                        foreach ($errosValidacao as $erro) {
                            $linhasComErro[] = [
                                'linha'         => $linhaIndex,
                                'coluna'        => $erro['coluna'],
                                'motivo'        => $erro['motivo'],
                                'como_corrigir' => $erro['como_corrigir'],
                            ];
                        }
                        continue;
                    }

                    $produto = $this->processarLinha($dados, $linhaIndex, $empresasIds);
                    if ($produto) {
                        $produto->save();
                        $importados++;
                    }
                } catch (\Exception $e) {
                    $linhasComErro[] = [
                        'linha'         => $linhaIndex,
                        'coluna'        => 'Geral',
                        'motivo'        => $e->getMessage(),
                        'como_corrigir' => 'Verifique os dados da linha e tente novamente',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Erro ao processar Excel', ['erro' => $e->getMessage()]);
            throw new \Exception('Erro ao processar o arquivo Excel: ' . $e->getMessage());
        }
    }

    /**
     * Converte valores string para boolean
     *
     * @param mixed $valor
     * @param bool $default
     * @return bool
     */
    private function converterParaBooleano($valor, bool $default = false): bool
    {
        if (is_null($valor) || $valor === '') {
            return $default;
        }

        $valorStr = strtolower(trim($valor));
        return in_array($valorStr, ['s', 'sim', '1', 'true', 'yes']);
    }

    /**
     * Detecta o formato real do arquivo baseado no conteúdo
     */
}
