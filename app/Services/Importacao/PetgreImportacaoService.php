<?php

namespace App\Services\Importacao;

use Illuminate\Http\UploadedFile;
use App\Models\Produto;
use App\Models\Categorias;
use App\Models\UnidadeMedida;
use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use OpenSpout\Reader\ODS\Reader as ODSReader;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;

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
        $usuarioAutenticado = Auth::user();
        $empresasIds = $usuarioAutenticado->empresas->pluck('id')->toArray();

        $total = 0;
        $importados = 0;
        $erros = [];

        DB::beginTransaction();
        try {
            // Criar reader baseado na extensão
            $extensao = strtolower($arquivo->getClientOriginalExtension());

            if ($extensao === 'xlsx') {
                $reader = new XLSXReader();
            } elseif ($extensao === 'xls') {
                $reader = new XLSXReader(); // OpenSpout trata XLS como XLSX
            } else {
                throw new \Exception('Formato de arquivo não suportado. Use apenas .xlsx ou .xls');
            }

            $reader->open($arquivo->getPathname());

            foreach ($reader->getSheetIterator() as $sheet) {
                $linhaIndex = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $linhaIndex++;

                    // Pular cabeçalho (linha 1)
                    if ($linhaIndex === 1) {
                        continue;
                    }

                    $dados = $row->toArray();
                    $total++;

                    // Validar linha não vazia
                    if (empty(array_filter($dados))) {
                        continue; // Pular linhas completamente vazias
                    }

                    try {
                        $produto = $this->processarLinha($dados, $linhaIndex, $empresasIds);

                        if ($produto) {
                            $produto->save();
                            $importados++;
                        }
                    } catch (\Exception $e) {
                        $erros[] = [
                            'linha' => $linhaIndex,
                            'mensagem' => $e->getMessage()
                        ];
                    }
                }
            }

            $reader->close();

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'total' => $total,
            'importados' => $importados,
            'erros' => $erros
        ];
    }

    /**
     * Valida se a estrutura da planilha é compatível com o formato Petgre
     *
     * @param array $cabecalho
     * @return bool
     */
    public function validarEstrutura(array $cabecalho): bool
    {
        $cabecalhoEsperado = $this->getCabecalhoEsperado();
        return $cabecalho === $cabecalhoEsperado;
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
     *
     * @param array $dados
     * @param int $linhaIndex
     * @param array $empresasIds
     * @return Produto|null
     */
    private function processarLinha(array $dados, int $linhaIndex, array $empresasIds): ?Produto
    {
        // Mapear dados da planilha para campos do produto
        $mapeamento = [
            'nome' => $dados[0] ?? null,
            'descricao' => $dados[1] ?? null,
            'categoria_nome' => $dados[2] ?? null,
            'unidade_medida_nome' => $dados[3] ?? null,
            'preco' => $dados[4] ?? null,
            'estoque' => $dados[5] ?? null,
            'marca' => $dados[6] ?? null,
            'sku' => $dados[7] ?? null,
            'preco_custo' => $dados[8] ?? null,
            'estoque_minimo' => $dados[9] ?? null,
            'peso' => $dados[10] ?? null,
            'altura' => $dados[11] ?? null,
            'largura' => $dados[12] ?? null,
            'comprimento' => $dados[13] ?? null,
            'ordem' => $dados[14] ?? null,
            'preco_promocional' => $dados[15] ?? null,
            'promocao_ate' => $dados[16] ?? null,
            'vende_granel' => $dados[17] ?? null,
            'tipo' => $dados[18] ?? null,
            'ativo' => $dados[19] ?? null,
            'destaque' => $dados[20] ?? null,
        ];

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
}