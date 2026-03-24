<?php

namespace App\Services\Produto;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use Rap2hpoutre\FastExcel\FastExcel;

class ProdutoImportacaoPlanilhaService
{
    /**
     * @return array{ok: true, payload: array<string, mixed>}|array{ok: false, http: int, error?: string, message: string}
     */
    public function importar(Request $request): array
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:xlsx,xls|max:5120',
            'tipo'    => 'required|string',
        ]);

        $arquivo = $request->file('arquivo');
        $tipo = $request->input('tipo', 'petgre');

        $extensao = strtolower($arquivo->getClientOriginalExtension());
        if (! in_array($extensao, ['xlsx', 'xls'], true)) {
            return [
                'ok'      => false,
                'http'    => 400,
                'error'   => 'Formato não suportado',
                'message' => 'Envie um arquivo Excel (.xlsx ou .xls).',
            ];
        }

        try {
            if ($arquivo->getSize() > 5242880) {
                return [
                    'ok'      => false,
                    'http'    => 400,
                    'error'   => 'Arquivo muito grande',
                    'message' => 'O arquivo deve ter no máximo 5MB.',
                ];
            }

            if ($tipo === 'petgre') {
                $serviceClass = \App\Services\Importacao\PetgreImportacaoService::class;
            } else {
                $serviceClass = 'App\\Services\\Importacao\\Terceiros\\' . Str::studly($tipo) . 'ImportacaoService';

                if (! class_exists($serviceClass)) {
                    return [
                        'ok'      => false,
                        'http'    => 422,
                        'error'   => 'Importação não disponível',
                        'message' => 'Este tipo de importação ainda não está disponível. Entre em contato com o suporte.',
                    ];
                }
            }

            $service = new $serviceClass();

            if ($tipo === 'petgre') {
                $cabecalho = $this->lerCabecalhoPlanilha($arquivo);
                if (! $service->validarEstrutura($cabecalho)) {
                    return [
                        'ok'      => false,
                        'http'    => 400,
                        'error'   => 'Formato incorreto',
                        'message' => 'A planilha não está no formato esperado. Baixe o modelo e tente novamente.',
                    ];
                }
            }

            $resultado = $service->importar($arquivo);

            return [
                'ok'      => true,
                'payload' => [
                    'message'            => 'Importação concluída',
                    'total'              => $resultado['total'],
                    'importados'         => $resultado['importados'],
                    'erros'              => $resultado['erros'],
                    'planilha_erros_url' => $resultado['planilha_erros_url'],
                    'linhas_com_erro'    => $resultado['linhas_com_erro'],
                ],
            ];
        } catch (\Exception $e) {
            return [
                'ok'      => false,
                'http'    => 500,
                'error'   => 'Não foi possível importar',
                'message' => 'Verifique o arquivo e tente novamente.',
            ];
        }
    }

    /**
     * Download do modelo Excel ou JSON de erro.
     */
    public function downloadModelo()
    {
        try {
            $linhas = collect([
                [
                    'Nome*'                     => 'Produto Exemplo',
                    'Descrição'                 => 'Descrição do produto exemplo',
                    'Categoria'                 => 'Rações',
                    'Unidade de Medida'         => 'Unidade',
                    'Preço*'                    => '29.90',
                    'Estoque'                   => '100',
                    'Marca'                     => 'Marca Exemplo',
                    'SKU'                       => 'PROD001',
                    'Preço de Custo'            => '20.00',
                    'Estoque Mínimo'            => '10',
                    'Peso (kg)'                 => '1.5',
                    'Altura (cm)'               => '10',
                    'Largura (cm)'              => '20',
                    'Comprimento (cm)'          => '30',
                    'Ordem'                     => '1',
                    'Preço Promocional'         => '25.90',
                    'Promoção Até (YYYY-MM-DD)' => '2024-12-31',
                    'Vende a Granel (S/N)'      => 'N',
                    'Tipo (produto/serviço)'    => 'produto',
                    'Ativo (S/N)'               => 'S',
                    'Destaque (S/N)'            => 'N',
                ],
            ]);

            $headerStyle = (new Style())
                ->setFontBold()
                ->setFontSize(14)
                ->setFontColor(Color::WHITE)
                ->setBackgroundColor('3B82F6');

            $tempFile = tempnam(sys_get_temp_dir(), 'modelo_') . '.xlsx';
            (new FastExcel($linhas))->headerStyle($headerStyle)->export($tempFile);

            return response()->download($tempFile, 'modelo_produtos_petgre.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Não foi possível gerar o modelo',
                'message' => 'Tente novamente em alguns instantes.',
            ], 500);
        }
    }

    /**
     * @return array{ok: true, path: string, filename: string}|array{ok: false, http: int, error: string, message: string}
     */
    public function resolverDownloadPlanilhaErros(): array
    {
        try {
            $usuarioAutenticado = Auth::user();
            $empresaId = $usuarioAutenticado->empresas->first()->id ?? null;

            if (! $empresaId) {
                return [
                    'ok'      => false,
                    'http'    => 403,
                    'error'   => 'Empresa não encontrada',
                    'message' => 'Selecione uma empresa para continuar.',
                ];
            }

            $path = "planilhas/empresa/{$empresaId}/importacao_produto_empresa_{$empresaId}.xlsx";

            if (! Storage::disk('local')->exists($path)) {
                return [
                    'ok'      => false,
                    'http'    => 404,
                    'error'   => 'Arquivo não encontrado',
                    'message' => 'Não há relatório de erros disponível no momento.',
                ];
            }

            $fullPath = Storage::disk('local')->path($path);

            return [
                'ok'       => true,
                'path'     => $fullPath,
                'filename' => 'erros_importacao_produtos.xlsx',
            ];
        } catch (\Exception $e) {
            return [
                'ok'      => false,
                'http'    => 500,
                'error'   => 'Não foi possível baixar',
                'message' => 'Tente novamente em alguns instantes.',
            ];
        }
    }

    public function lerCabecalhoPlanilha($arquivo): array
    {
        $extensao = strtolower($arquivo->getClientOriginalExtension());

        Log::info('Tentando ler cabeçalho Excel', [
            'extensao'        => $extensao,
            'tamanho_arquivo' => filesize($arquivo->getPathname()),
        ]);

        try {
            $linhas = (new FastExcel)->import($arquivo->getPathname());

            if ($linhas->isNotEmpty()) {
                $primeiraLinha = $linhas->first();
                $cabecalho = array_keys($primeiraLinha);

                $cabecalho = array_map('trim', $cabecalho);
                $cabecalho = array_filter($cabecalho, function ($item) {
                    return $item !== null && $item !== '';
                });

                Log::info('Cabeçalho lido com FastExcel', ['cabecalho' => $cabecalho, 'quantidade' => count($cabecalho)]);

                return array_values($cabecalho);
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Erro ao ler cabeçalho com FastExcel', ['erro' => $e->getMessage()]);
            throw new \Exception('Não foi possível ler o arquivo. Verifique se é um Excel válido.');
        }
    }
}
