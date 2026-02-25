<?php

namespace App\Http\Controllers;

use App\Http\Requests\Empresa\EmpresaStoreRequest;
use App\Http\Requests\Empresa\EmpresaUpdateRequest;
use App\Http\Requests\Empresa\EmpresaUploadImageRequest;
use App\Http\Resources\EmpresaResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Empresa;
use App\Models\User;
use App\Models\EmpresaEndereco;
use App\Models\Bairro;
use App\Helpers\FormatHelper;
use App\Http\Resources\Usuario\UsuarioResource;
use App\Models\UsuarioEnderecos;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Helpers\VerificaEmpresa;
use App\Services\EmailService;
use App\Mail\NovoLojistaMail;
use Illuminate\Support\Str;

class EmpresaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $usuario = Auth::user();

        $empresas = $usuario->usuarioEmpresas()
            ->with(['empresa'])
            ->get()
            ->pluck('empresa');

        return response()->json([
            'success' => true,
            'empresas' => EmpresaResource::collection($empresas)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EmpresaStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            // Prepara os dados da empresa
            $dadosEmpresa = $request->only([
                'tipo_pessoa',
                'razao_social',
                'nome_fantasia',
                'email',
                'telefone',
                'cpf_cnpj',
                'nicho_id'
            ]);

            // Gera o slug automaticamente baseado no nome fantasia ou razão social
            $textoParaSlug = $dadosEmpresa['nome_fantasia'] ?? $dadosEmpresa['razao_social'];
            $slugBase = FormatHelper::formatSlug($textoParaSlug);
            $dadosEmpresa['slug'] = $slugBase;
            if (Empresa::where('slug', $dadosEmpresa['slug'])->exists()) {
                do {
                    $dadosEmpresa['slug'] = $slugBase . '-' . Str::random(8);
                } while (Empresa::where('slug', $dadosEmpresa['slug'])->exists());
            }
            $dadosEmpresa['telefone'] = FormatHelper::formatOnlyNumbers($dadosEmpresa['telefone']);

            // Cria a empresa
            $empresa = Empresa::create($dadosEmpresa);

            // Cria o endereço da empresa se foi enviado
            if ($request->has('endereco')) {
                $dadosEndereco = $request->input('endereco');
                $dadosEndereco['empresa_id'] = $empresa->id;

                // Import necessário para EmpresaEndereco
                $endereco = EmpresaEndereco::create($dadosEndereco);
            }

            // Prepara os dados do usuário administrador
            $dadosUsuario = $request->input('usuario_admin');

            // Criptografa a senha e formata o telefone (remove caracteres especiais)
            $dadosUsuario['password'] = Hash::make($dadosUsuario['password']);
            $dadosUsuario['telefone'] = $dadosUsuario['telefone'];
            $dadosUsuario['is_master'] = true; // Usuário criado junto com empresa é master
            $dadosUsuario['tipo_cadastro'] = 0; // 0 = Empresa

            // Cria o usuário administrador
            $usuario = User::create($dadosUsuario);

            // Sincronizar permissões do usuário administrador
            $permissoes = $request->input('usuario_admin.permissoes', []);
            // Se for array associativo, extrair apenas os valores
            if (count($permissoes) > 0 && is_string(array_keys($permissoes)[0])) {
                $permissoes = array_values($permissoes);
            }
            $permissoesIds = array_map('intval', $permissoes);
            $usuario->permissoes()->sync($permissoesIds);

            // Associa o usuário à empresa
            $usuario->empresas()->attach($empresa->id);

            // Criar endereço do usuário administrador usando o mesmo endereço da empresa
            if ($endereco) {
                UsuarioEnderecos::create([
                    'usuario_id' => $usuario->id,
                    'cep' => $endereco->cep,
                    'rua' => $endereco->logradouro,
                    'numero' => $endereco->numero,
                    'complemento' => $endereco->complemento,
                    'bairro' => $endereco->bairro,
                    'cidade' => $endereco->cidade,
                    'estado' => $endereco->estado,
                    'ponto_referencia' => $endereco->ponto_referencia,
                    'observacoes' => $endereco->observacoes,
                    'ativo' => true,
                ]);
            }

            // Cria as configurações da empresa
            $configuracoes = $empresa->configuracoes()->create([
                'empresa_id' => $empresa->id,
                'faz_entrega' => false,
                'faz_retirada' => true,
                'a_combinar' => false,
                'valor_entrega_padrao' => 10.00,
                'valor_entrega_minimo' => 10.00,
            ]);

            // Cria o horário da empresa
            $horario = $empresa->horarios()->create([
                'empresa_id' => $empresa->id,
                'dia_semana' => 'segunda',
                'slug' => 'segunda',
                'horario_inicio' => '08:00',
                'horario_fim' => '18:00',
                'padrao' => true,
            ]);

            DB::commit();

            // Enviar email de boas-vindas para o novo lojista
            try {
                $emailService = app(EmailService::class);
                $emailService->sendMailable($usuario->email, new NovoLojistaMail($empresa, $usuario));
            } catch (\Exception $emailException) {
                // Log do erro mas não falha a criação da empresa
                \Log::error('Erro ao enviar email de boas-vindas: ' . $emailException->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Empresa criada com sucesso',
                'empresa' => new EmpresaResource($empresa),
                'usuario' => new UsuarioResource($usuario),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload ou atualização de logo/banner da empresa
     */
    public function uploadImage(EmpresaUploadImageRequest $request, string $id)
    {
        try {
            // Verificar se o usuário autenticado tem acesso a esta empresa
            if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int)$id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar esta empresa.'
                ], 403);
            }

            $empresa = Empresa::findOrFail($id);
            $tipo = $request->query('tipo'); // 'banner' ou 'logo'
            $dadosAtualizacao = [];

            if ($tipo === 'banner' && $request->hasFile('banner')) {
                // Remove banner anterior se existir
                if ($empresa->path_banner) {
                    $bannerPathRelativo = str_replace(env('CLOUDFLARE_R2_PUBLIC_URL') . '/', '', $empresa->path_banner);
                    Storage::disk('r2')->delete($bannerPathRelativo);
                }

                $bannerPath = $request->file('banner')->store("empresas/banners/{$id}/banner", 'r2');
                $dadosAtualizacao['path_banner'] = env('CLOUDFLARE_R2_PUBLIC_URL') . '/' . $bannerPath;
            } elseif ($tipo === 'logo' && $request->hasFile('logo')) {
                // Remove logo anterior se existir
                if ($empresa->path_logo) {
                    $logoPathRelativo = str_replace(env('CLOUDFLARE_R2_PUBLIC_URL') . '/', '', $empresa->path_logo);
                    Storage::disk('r2')->delete($logoPathRelativo);
                }

                $logoPath = $request->file('logo')->store("empresas/logos/{$id}/logo", 'r2');
                $dadosAtualizacao['path_logo'] = env('CLOUDFLARE_R2_PUBLIC_URL') . '/' . $logoPath;
            } elseif (!$tipo) {
                // Upload de ambos se nenhum tipo específico foi informado
                if ($request->hasFile('banner')) {
                    if ($empresa->path_banner) {
                        $bannerPathRelativo = str_replace(env('CLOUDFLARE_R2_PUBLIC_URL') . '/', '', $empresa->path_banner);
                        Storage::disk('r2')->delete($bannerPathRelativo);
                    }
                    $bannerPath = $request->file('banner')->store("empresas/banners/{$id}/banner", 'r2');
                    $dadosAtualizacao['path_banner'] = env('CLOUDFLARE_R2_PUBLIC_URL') . '/' . $bannerPath;
                }

                if ($request->hasFile('logo')) {
                    if ($empresa->path_logo) {
                        $logoPathRelativo = str_replace(env('CLOUDFLARE_R2_PUBLIC_URL') . '/', '', $empresa->path_logo);
                        Storage::disk('r2')->delete($logoPathRelativo);
                    }
                    $logoPath = $request->file('logo')->store("empresas/logos/{$id}/logo", 'r2');
                    $dadosAtualizacao['path_logo'] = env('CLOUDFLARE_R2_PUBLIC_URL') . '/' . $logoPath;
                }
            }

            if (!empty($dadosAtualizacao)) {
                $empresa->update($dadosAtualizacao);

                return response()->json([
                    'success' => true,
                    'message' => 'Imagem(ns) atualizada(s) com sucesso',
                    'empresa' => new EmpresaResource($empresa)
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Nenhuma imagem foi enviada'
            ], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa não encontrada'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        try {
            // Verificar se o usuário autenticado tem acesso a esta empresa
            if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int)$id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar esta empresa.'
                ], 403);
            }

            // Verifica se deve retornar apenas dados básicos, enviar via query param na url
            $basic = filter_var($request->query('basic', false), FILTER_VALIDATE_BOOLEAN);

            if ($basic) {
                // Retorna apenas informações básicas da empresa
                $empresa = Empresa::findOrFail($id);

                return response()->json([
                    'success' => true,
                    'empresa' => [
                        'id' => $empresa->id,
                        'razao_social' => $empresa->razao_social,
                        'nome_fantasia' => $empresa->nome_fantasia,
                        'slug' => $empresa->slug,
                        'email' => $empresa->email,
                        'telefone' => $empresa->telefone,
                        'cnpj' => $empresa->cnpj,
                        'ativo' => $empresa->ativo,
                        'created_at' => $empresa->created_at,
                        'updated_at' => $empresa->updated_at,
                    ]
                ]);
            }

            // Retorna informações completas com relacionamentos
            $empresa = Empresa::with([
                'nicho',
                'endereco',
                'configuracoes',
                'horarios',
                'assinatura.plano',
                'formasPagamentos.formaPagamento',
                'bairrosEntregas.bairro',
                'usuarios.usuario.permissoes'
            ])->findOrFail($id);

            $additionalData = [];

            // Se cadastro não estiver completo, calcular progresso e itens pendentes
            if (!$empresa->cadastro_completo) {
                $progresso = $this->calcularProgressoCadastro($empresa);

                // Adicionar informações de progresso aos dados adicionais
                $additionalData['cadastro'] = [
                    'progresso_porcentagem' => $progresso['porcentagem'],
                    'itens_completos' => $progresso['itens_completos'],
                    'total_itens' => $progresso['total_itens'],
                    'itens_pendentes' => $progresso['itens_pendentes'],
                    'completo' => $progresso['completo']
                ];

                // Verificar se agora está completo e atualizar se necessário
                if ($progresso['completo']) {
                    $this->verificarCadastroCompleto($empresa);
                    // Recarregar para pegar o status atualizado
                    $empresa->refresh();
                }
            }

            return response()->json([
                'success' => true,
                'empresa' => new EmpresaResource($empresa, $additionalData)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa não encontrada'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EmpresaUpdateRequest $request, string $id)
    {
        try {
            // Verificar se o usuário autenticado tem acesso a esta empresa
            if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int)$id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar esta empresa.'
                ], 403);
            }

            DB::beginTransaction();

            $empresa = Empresa::findOrFail($id);

            // Prepara os dados para atualização
            $dadosEmpresa = $request->all();
            // Se foi enviado um novo nome fantasia ou razão social, gera novo slug
            if (isset($dadosEmpresa['nome_fantasia']) || isset($dadosEmpresa['razao_social'])) {
                $textoParaSlug = $dadosEmpresa['nome_fantasia'] ?? $empresa->nome_fantasia ?? $dadosEmpresa['razao_social'] ?? $empresa->razao_social;
                $slugBase = FormatHelper::formatSlug($textoParaSlug);
                $dadosEmpresa['slug'] = $slugBase;
                $slugExiste = fn ($s) => Empresa::where('slug', $s)->where('id', '!=', $empresa->id)->exists();
                if ($slugExiste($dadosEmpresa['slug'])) {
                    do {
                        $dadosEmpresa['slug'] = $slugBase . '-' . Str::random(8);
                    } while ($slugExiste($dadosEmpresa['slug']));
                }
            }

            // Formata telefone se foi enviado
            if (isset($dadosEmpresa['telefone'])) {
                $dadosEmpresa['telefone'] = FormatHelper::formatOnlyNumbers($dadosEmpresa['telefone']);
            }

            // Upload de banner se foi enviada
            if ($request->hasFile('path_banner')) {
                // Remove banner anterior se existir
                if ($empresa->path_banner) {
                    Storage::disk('public')->delete($empresa->path_banner);
                }
                $bannerPath = $request->file('path_banner')->store('empresas/banners', 'public');
                $dadosEmpresa['path_banner'] = $bannerPath;
            }

            // Atualiza dados básicos da empresa
            $dadosBasicos = collect($dadosEmpresa)->only([
                'razao_social',
                'nome_fantasia',
                'slug',
                'email',
                'telefone',
                'cnpj',
                'path_logo',
                'path_banner',
                'nicho_id',
                'ativo'
            ])->toArray();

            $empresa->update($dadosBasicos);

            // Atualiza configurações se foram enviadas
            if (isset($dadosEmpresa['configuracoes'])) {
                $empresa->configuracoes()->updateOrCreate(
                    ['empresa_id' => $id],
                    $dadosEmpresa['configuracoes']
                );
            }

            // Atualiza horários se foram enviados
            if (isset($dadosEmpresa['horarios']) && is_array($dadosEmpresa['horarios'])) {
                // Remove horários existentes
                $empresa->horarios()->delete();

                // Adiciona novos horários
                foreach ($dadosEmpresa['horarios'] as $horario) {
                    // Gera slug automaticamente baseado no dia da semana
                    $horario['slug'] = FormatHelper::formatSlug($horario['dia_semana']);
                    $empresa->horarios()->create($horario);
                }
            }

            // Atualiza endereço se foi enviado
            if (isset($dadosEmpresa['endereco'])) {
                $empresa->endereco()->updateOrCreate(
                    ['empresa_id' => $id],
                    $dadosEmpresa['endereco']
                );
            }

            // Atualiza formas de pagamento se foram enviadas
            if (isset($dadosEmpresa['formas_pagamento']) && is_array($dadosEmpresa['formas_pagamento'])) {
                // Remove formas de pagamento existentes
                $empresa->formasPagamentos()->delete();

                // Adiciona novas formas de pagamento
                foreach ($dadosEmpresa['formas_pagamento'] as $forma) {
                    $empresa->formasPagamentos()->create($forma);
                }
            }

            // Atualiza bairros de entrega se foram enviados
            if (isset($dadosEmpresa['bairros_entrega']) && is_array($dadosEmpresa['bairros_entrega'])) {
                // Remove bairros de entrega existentes
                $empresa->bairrosEntregas()->delete();

                // Adiciona novos bairros de entrega
                foreach ($dadosEmpresa['bairros_entrega'] as $bairro) {
                    $empresa->bairrosEntregas()->create($bairro);
                }
            }

            // Verifica se o cadastro está completo após a atualização
            if (!$empresa->cadastro_completo) {
                $this->verificarCadastroCompleto($empresa);
            }

            DB::commit();

            // Recarrega a empresa com relacionamentos atualizados
            $empresa->load(['configuracoes', 'horarios', 'formasPagamentos.formaPagamento', 'endereco', 'bairrosEntregas.bairro']);

            return response()->json([
                'success' => true,
                'message' => 'Empresa atualizada com sucesso',
                'empresa' => new EmpresaResource($empresa)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Retorna status de abertura da loja (para indicador no painel lojista).
     */
    public function status(Request $request, string $id)
    {
        try {
            if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int)$id)) {
                return response()->json(['success' => false, 'message' => 'Acesso negado.'], 403);
            }
            $empresa = Empresa::with(['horarios', 'pausasAgendadas'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'empresa_aberta' => $empresa->isAberta(),
                'fechado_ate' => $empresa->getFechadoAte(),
                'fechada_manual' => (bool) $empresa->fechada_manual,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Empresa não encontrada'], 404);
        }
    }

    /**
     * Fechar ou abrir a loja manualmente (override de horário/pausas). Emergência sem usar Pausas agendadas.
     */
    public function statusManual(Request $request, string $id)
    {
        $request->validate(['fechada_manual' => 'required|boolean']);
        try {
            if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int) $id)) {
                return response()->json(['success' => false, 'message' => 'Acesso negado.'], 403);
            }
            $empresa = Empresa::findOrFail($id);
            $empresa->update(['fechada_manual' => $request->boolean('fechada_manual')]);
            $empresa->load(['horarios', 'pausasAgendadas']);
            return response()->json([
                'success' => true,
                'empresa_aberta' => $empresa->isAberta(),
                'fechado_ate' => $empresa->getFechadoAte(),
                'fechada_manual' => (bool) $empresa->fechada_manual,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Empresa não encontrada'], 404);
        }
    }

    /**
     * Check if company registration is complete.
     */
    public function verificarCadastro(Request $request, string $id)
    {
        try {
            // Verificar se o usuário autenticado tem acesso a esta empresa
            if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int)$id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar esta empresa.'
                ], 403);
            }

            $empresa = Empresa::with(['endereco', 'configuracoes', 'formasPagamentos', 'horarios', 'bairrosEntregas'])->findOrFail($id);

            $itens = [];
            $itens[] = ['chave' => 'endereco', 'label' => 'Endereço da empresa', 'ok' => (bool) $empresa->endereco];
            $itens[] = ['chave' => 'configuracoes', 'label' => 'Configurações da empresa', 'ok' => (bool) $empresa->configuracoes];
            $itens[] = ['chave' => 'whatsapp_pedidos', 'label' => 'WhatsApp para pedidos', 'ok' => $empresa->configuracoes ? !empty($empresa->configuracoes->whatsapp_pedidos) : false];
            $itens[] = ['chave' => 'formas_pagamento', 'label' => 'Pelo menos uma forma de pagamento', 'ok' => !$empresa->formasPagamentos->isEmpty()];
            $itens[] = ['chave' => 'horarios', 'label' => 'Horários de funcionamento', 'ok' => !$empresa->horarios->isEmpty()];
            $itens[] = ['chave' => 'bairros_entrega', 'label' => 'Pelo menos um bairro de entrega', 'ok' => !$empresa->bairrosEntregas->isEmpty()];

            $total = count($itens);
            $ok = collect($itens)->where('ok', true)->count();
            $percentual = $total > 0 ? (int) round(($ok / $total) * 100) : 0;
            $itens_pendentes = collect($itens)->where('ok', false)->pluck('label')->values()->all();
            $cadastroCompleto = $percentual === 100;

            return response()->json([
                'success' => true,
                'cadastro_completo' => $cadastroCompleto,
                'percentual' => $percentual,
                'itens_pendentes' => $itens_pendentes,
                'empresa_id' => $empresa->id,
                'empresa_nome' => $empresa->nome_fantasia ?? $empresa->razao_social
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa não encontrada'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna todos os bairros disponíveis para entrega na cidade da empresa
     */
    public function bairrosDisponiveis(Request $request, string $empresaId)
    {
        try {
            // Verificar se o usuário autenticado tem acesso a esta empresa
            if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int)$empresaId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar esta empresa.'
                ], 403);
            }

            $empresa = Empresa::with('endereco')->findOrFail($empresaId);

            // Verifica se a empresa tem endereço cadastrado
            if (!$empresa->endereco) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não possui endereço cadastrado'
                ], 400);
            }

            // Busca todos os bairros da cidade da empresa
            $bairros = Bairro::where('cidade', $empresa->endereco->cidade)
                ->where('estado', $empresa->endereco->estado)
                ->orderBy('nome')
                ->get(['id', 'nome']);

            return response()->json([
                'success' => true,
                'bairros' => $bairros
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa não encontrada'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica se o cadastro da empresa está completo e atualiza o campo cadastro_completo
     */
    private function verificarCadastroCompleto(Empresa $empresa)
    {
        $cadastroCompleto = true;

        // Verifica se existe endereço
        if (!$empresa->endereco) {
            $cadastroCompleto = false;
        }

        // Verifica se existe configurações
        if (!$empresa->configuracoes) {
            $cadastroCompleto = false;
        } else {
            // Verifica se o WhatsApp de pedidos está preenchido (essencial para receber pedidos)
            if (empty($empresa->configuracoes->whatsapp_pedidos)) {
                $cadastroCompleto = false;
            }
        }

        // Verifica se existe pelo menos uma forma de pagamento
        if ($empresa->formasPagamentos->isEmpty()) {
            $cadastroCompleto = false;
        }

        // Verifica se existe pelo menos um horário
        if ($empresa->horarios->isEmpty()) {
            $cadastroCompleto = false;
        }

        // Verifica se existe pelo menos um bairro de entrega
        if ($empresa->bairrosEntregas->isEmpty()) {
            $cadastroCompleto = false;
        }

        // Se todas as verificações passaram, marca como cadastro completo
        if ($cadastroCompleto) {
            $empresa->update(['cadastro_completo' => true]);
        }
    }

    /**
     * Calcula o progresso do cadastro da empresa e identifica itens pendentes
     */
    private function calcularProgressoCadastro(Empresa $empresa)
    {
        $itensPendentes = [];
        $itensCompletos = 0;
        $totalItens = 6; // endereço, configurações, whatsapp pedidos, formas de pagamento, horários, bairros

        // Verifica se existe endereço
        if (!$empresa->endereco) {
            $itensPendentes[] = 'Endereço da empresa';
        } else {
            $itensCompletos++;
        }

        // Verifica se existe configurações
        if (!$empresa->configuracoes) {
            $itensPendentes[] = 'Configurações da empresa';
        } else {
            $itensCompletos++;

            // Verifica se o WhatsApp de pedidos está preenchido (essencial para receber pedidos)
            if (empty($empresa->configuracoes->whatsapp_pedidos)) {
                $itensPendentes[] = 'Número do WhatsApp para receber pedidos';
            } else {
                $itensCompletos++;
            }
        }

        // Verifica se existe pelo menos uma forma de pagamento
        if ($empresa->formasPagamentos->isEmpty()) {
            $itensPendentes[] = 'Formas de pagamento';
        } else {
            $itensCompletos++;
        }

        // Verifica se existe pelo menos um horário
        if ($empresa->horarios->isEmpty()) {
            $itensPendentes[] = 'Horários de funcionamento';
        } else {
            $itensCompletos++;
        }

        // Verifica se existe pelo menos um bairro de entrega
        if ($empresa->bairrosEntregas->isEmpty()) {
            $itensPendentes[] = 'Bairros de entrega';
        } else {
            $itensCompletos++;
        }

        $porcentagem = round(($itensCompletos / $totalItens) * 100);

        return [
            'porcentagem' => $porcentagem,
            'itens_completos' => $itensCompletos,
            'total_itens' => $totalItens,
            'itens_pendentes' => $itensPendentes,
            'completo' => $itensCompletos === $totalItens
        ];
    }
}
