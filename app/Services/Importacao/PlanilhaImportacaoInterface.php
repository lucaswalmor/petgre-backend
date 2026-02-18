<?php

namespace App\Services\Importacao;

use Illuminate\Http\UploadedFile;

interface PlanilhaImportacaoInterface
{
    /**
     * Processa a importação da planilha
     *
     * @param UploadedFile $arquivo
     * @return array Retorna array com: ['total' => int, 'importados' => int, 'erros' => array]
     */
    public function importar(UploadedFile $arquivo): array;

    /**
     * Valida se a estrutura da planilha é compatível
     *
     * @param array $cabecalho
     * @return bool
     */
    public function validarEstrutura(array $cabecalho): bool;

    /**
     * Retorna o cabeçalho esperado da planilha
     *
     * @return array
     */
    public function getCabecalhoEsperado(): array;
}