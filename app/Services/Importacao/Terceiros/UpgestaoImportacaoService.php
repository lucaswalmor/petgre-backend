<?php

namespace App\Services\Importacao\Terceiros;

use Illuminate\Http\UploadedFile;
use App\Services\Importacao\PlanilhaImportacaoInterface;

class UpgestaoImportacaoService implements PlanilhaImportacaoInterface
{
    /**
     * Processa a importação da planilha UpGestao
     *
     * @param UploadedFile $arquivo
     * @return array
     */
    public function importar(UploadedFile $arquivo): array
    {
        // TODO: implementar mapeamento de colunas do ERP Upgestao

        // Por enquanto, retorna um resultado vazio válido
        return [
            'total' => 0,
            'importados' => 0,
            'erros' => 0,
            'planilha_erros_url' => null,
            'linhas_com_erro' => []
        ];
    }

    /**
     * Valida se a estrutura da planilha é compatível com o formato UpGestao
     *
     * @param array $cabecalho
     * @return bool
     */
    public function validarEstrutura(array $cabecalho): bool
    {
        // TODO: implementar mapeamento de colunas do ERP Upgestao

        // Por enquanto, sempre retorna true
        return true;
    }

    /**
     * Retorna o cabeçalho esperado da planilha UpGestao
     *
     * @return array
     */
    public function getCabecalhoEsperado(): array
    {
        // TODO: implementar mapeamento de colunas do ERP Upgestao

        // Por enquanto, retorna um array vazio
        return [];
    }
}