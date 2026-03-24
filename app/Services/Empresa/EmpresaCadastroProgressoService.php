<?php

namespace App\Services\Empresa;

use App\Models\Empresa;
use App\Models\EmpresaFaturamento;
use App\Models\User;

class EmpresaCadastroProgressoService
{
    public function verificarCadastroCompleto(Empresa $empresa): void
    {
        $cadastroCompleto = true;

        if (! $empresa->endereco) {
            $cadastroCompleto = false;
        }

        if (! $empresa->configuracoes) {
            $cadastroCompleto = false;
        } elseif (empty($empresa->configuracoes->whatsapp_pedidos)) {
            $cadastroCompleto = false;
        }

        if ($empresa->formasPagamentos->isEmpty()) {
            $cadastroCompleto = false;
        }

        if ($empresa->horarios->isEmpty()) {
            $cadastroCompleto = false;
        }

        if ($empresa->bairrosEntregas->isEmpty()) {
            $cadastroCompleto = false;
        }

        if ($empresa->is_matriz) {
            $master = User::where('is_master', true)
                ->whereHas('usuarioEmpresas', function ($q) use ($empresa) {
                    $q->where('empresa_id', $empresa->id);
                })
                ->first();

            if ($master) {
                $faturamento = EmpresaFaturamento::where('usuario_id', $master->id)->first();
                if (! $faturamento || empty($faturamento->nome_titular) || empty($faturamento->cpf_cnpj)) {
                    $cadastroCompleto = false;
                }
            }
        }

        if ($cadastroCompleto) {
            $empresa->update(['cadastro_completo' => true]);
        }
    }

    /**
     * @return array{porcentagem: float|int, itens_completos: int, total_itens: int, itens_pendentes: array<int, array<string, string>>, completo: bool}
     */
    public function calcularProgressoCadastro(Empresa $empresa): array
    {
        $itensPendentes = [];
        $itensCompletos = 0;
        $totalItens = 7;

        if (! $empresa->endereco) {
            $itensPendentes[] = [
                'titulo'    => 'Endereço da empresa',
                'navegacao' => 'Configurações → Empresa → Aba "Informações Gerais"',
                'campo'     => 'Preencha CEP, Logradouro, Número, Bairro, Cidade e Estado',
            ];
        } else {
            $itensCompletos++;
        }

        if (! $empresa->configuracoes) {
            $itensPendentes[] = [
                'titulo'    => 'Configurações da empresa',
                'navegacao' => 'Configurações → Empresa → Aba "Configurações"',
                'campo'     => 'Configure os dados básicos da empresa',
            ];
        } else {
            $itensCompletos++;

            if (empty($empresa->configuracoes->whatsapp_pedidos)) {
                $itensPendentes[] = [
                    'titulo'    => 'Número do WhatsApp para receber pedidos',
                    'navegacao' => 'Configurações → Empresa → Aba "Configurações"',
                    'campo'     => 'Campo "WhatsApp Pedidos" (ESSENCIAL para receber pedidos dos clientes)',
                ];
            } else {
                $itensCompletos++;
            }
        }

        if ($empresa->formasPagamentos->isEmpty()) {
            $itensPendentes[] = [
                'titulo'    => 'Formas de pagamento',
                'navegacao' => 'Configurações → Empresa → Aba "Horários & Pagamento"',
                'campo'     => 'Ative pelo menos uma forma de pagamento (Dinheiro, PIX, Cartão, etc.)',
            ];
        } else {
            $itensCompletos++;
        }

        if ($empresa->horarios->isEmpty()) {
            $itensPendentes[] = [
                'titulo'    => 'Horários de funcionamento',
                'navegacao' => 'Configurações → Empresa → Aba "Horários & Pagamento"',
                'campo'     => 'Configure o horário de abertura e fechamento para pelo menos um dia da semana',
            ];
        } else {
            $itensCompletos++;
        }

        if ($empresa->bairrosEntregas->isEmpty()) {
            $itensPendentes[] = [
                'titulo'    => 'Bairros de entrega',
                'navegacao' => 'Configurações → Empresa → Aba "Entregas"',
                'campo'     => 'Ative pelo menos um bairro para entrega e defina o valor do frete',
            ];
        } else {
            $itensCompletos++;
        }

        if ($empresa->is_matriz) {
            $master = User::where('is_master', true)
                ->whereHas('usuarioEmpresas', function ($q) use ($empresa) {
                    $q->where('empresa_id', $empresa->id);
                })
                ->first();

            if ($master) {
                $faturamento = EmpresaFaturamento::where('usuario_id', $master->id)->first();
                if (! $faturamento || empty($faturamento->nome_titular) || empty($faturamento->cpf_cnpj)) {
                    $itensPendentes[] = [
                        'titulo'    => 'Dados de faturamento',
                        'navegacao' => 'Configurações → Empresa → Aba "Dados de Faturamento"',
                        'campo'     => 'Preencha nome do titular, CPF/CNPJ, email, telefone e chave PIX',
                    ];
                } else {
                    $itensCompletos++;
                }
            } else {
                $itensPendentes[] = [
                    'titulo'    => 'Dados de faturamento',
                    'navegacao' => 'Configurações → Empresa → Aba "Dados de Faturamento"',
                    'campo'     => 'Preencha nome do titular, CPF/CNPJ, email, telefone e chave PIX',
                ];
            }
        } else {
            $totalItens = 6;
        }

        $porcentagem = round(($itensCompletos / $totalItens) * 100);

        return [
            'porcentagem'     => $porcentagem,
            'itens_completos' => $itensCompletos,
            'total_itens'     => $totalItens,
            'itens_pendentes' => $itensPendentes,
            'completo'        => $itensCompletos === $totalItens,
        ];
    }
}
