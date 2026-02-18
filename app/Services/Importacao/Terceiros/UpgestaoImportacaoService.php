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
    }

    /**
     * Retorna o cabeçalho esperado da planilha UpGestao
     *
     * @return array
     */
    public function getCabecalhoEsperado(): array
    {
        // TODO: implementar mapeamento de colunas do ERP Upgestao
    }
}