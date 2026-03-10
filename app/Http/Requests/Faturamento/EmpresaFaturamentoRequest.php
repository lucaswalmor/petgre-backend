<?php

namespace App\Http\Requests\Faturamento;

use Illuminate\Foundation\Http\FormRequest;

class EmpresaFaturamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $store = $this->isMethod('post');
        $rules = [
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'chave_pix' => 'nullable|string|max:255',
            'tipo_chave_pix' => 'nullable|in:cpf,cnpj,email,telefone,aleatoria',
        ];
        if ($store) {
            $rules['nome_titular'] = 'nullable|string|max:255';
            $rules['tipo_documento_titular'] = 'nullable|in:cpf,cnpj';
            $rules['cpf_cnpj'] = 'nullable|string|max:20';
        }
        return $rules;
    }
}
