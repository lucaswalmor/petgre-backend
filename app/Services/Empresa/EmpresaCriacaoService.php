<?php

namespace App\Services\Empresa;

use App\Helpers\FormatHelper;
use App\Http\Requests\Empresa\EmpresaStoreRequest;
use App\Mail\NovoLojistaMail;
use App\Models\Empresa;
use App\Models\EmpresaEndereco;
use App\Models\User;
use App\Models\UsuarioEnderecos;
use App\Services\EmailService;
use App\Services\FaturamentoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmpresaCriacaoService
{
    public function __construct(
        private FaturamentoService $faturamentoService
    ) {}

    /**
     * @return array{ok: true, empresa: Empresa}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function criarFilial(EmpresaStoreRequest $request): array
    {
        $user = Auth::user();
        if (! $user) {
            return ['ok' => false, 'http' => 401, 'body' => ['success' => false, 'message' => 'Não autenticado.']];
        }

        $empresaId = $request->header('x-empresa-id');
        if (empty($empresaId) || ! $user->empresas()->where('empresas.id', (int) $empresaId)->exists()) {
            return ['ok' => false, 'http' => 403, 'body' => ['success' => false, 'message' => 'Header x-empresa-id obrigatório e você deve ter vínculo com a empresa.']];
        }

        $empresaMatriz = Empresa::where('is_matriz', true)->find((int) $empresaId);
        if (! $empresaMatriz) {
            return ['ok' => false, 'http' => 403, 'body' => ['success' => false, 'message' => 'Apenas empresa matriz pode ter filiais.']];
        }

        if (! $user->isMaster() && ! $user->hasPermission('empresas.criar_filial')) {
            return ['ok' => false, 'http' => 403, 'body' => ['success' => false, 'message' => 'Sem permissão para criar filial.']];
        }

        try {
            DB::beginTransaction();

            $dadosEmpresa = $request->only(['tipo_pessoa', 'razao_social', 'nome_fantasia', 'email', 'telefone', 'cpf_cnpj', 'nicho_id']);
            $dadosEmpresa['empresa_matriz_id'] = $empresaMatriz->id;
            $dadosEmpresa['is_matriz'] = false;

            $textoParaSlug = $dadosEmpresa['nome_fantasia'] ?? $dadosEmpresa['razao_social'];
            $slugBase = FormatHelper::formatSlug($textoParaSlug);
            $dadosEmpresa['slug'] = $slugBase;
            if (Empresa::where('slug', $dadosEmpresa['slug'])->exists()) {
                do {
                    $dadosEmpresa['slug'] = $slugBase . '-' . Str::random(8);
                } while (Empresa::where('slug', $dadosEmpresa['slug'])->exists());
            }
            $dadosEmpresa['telefone'] = FormatHelper::formatOnlyNumbers($dadosEmpresa['telefone']);

            $empresa = Empresa::create($dadosEmpresa);

            if ($request->has('endereco')) {
                $dadosEndereco = $request->input('endereco');
                $dadosEndereco['empresa_id'] = $empresa->id;
                EmpresaEndereco::create($dadosEndereco);
            }

            $empresa->configuracoes()->create([
                'empresa_id'             => $empresa->id,
                'faz_entrega'            => false,
                'faz_retirada'           => true,
                'a_combinar'             => false,
                'valor_entrega_padrao'   => 10.00,
                'valor_entrega_minimo'   => 10.00,
            ]);

            $empresa->horarios()->create([
                'empresa_id'     => $empresa->id,
                'dia_semana'     => 'segunda',
                'slug'           => 'segunda',
                'horario_inicio' => '08:00',
                'horario_fim'    => '18:00',
                'padrao'         => true,
            ]);

            $masterMatriz = User::where('is_master', true)->whereHas('usuarioEmpresas', function ($q) use ($empresaMatriz) {
                $q->where('empresa_id', $empresaMatriz->id);
            })->first();
            if ($masterMatriz) {
                $masterMatriz->empresas()->attach($empresa->id);
            }
            if (! $user->empresas()->where('empresas.id', $empresa->id)->exists()) {
                $user->empresas()->attach($empresa->id);
            }

            DB::commit();

            if ($masterMatriz) {
                $this->faturamentoService->recalcularValorAssinatura($masterMatriz->id);
            }

            return ['ok' => true, 'empresa' => $empresa];
        } catch (\Exception $e) {
            DB::rollBack();

            return ['ok' => false, 'http' => 500, 'body' => ['success' => false, 'error' => $e->getMessage()]];
        }
    }

    /**
     * @return array{ok: true, empresa: Empresa, usuario: User}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function criarMatriz(EmpresaStoreRequest $request): array
    {
        try {
            DB::beginTransaction();

            $dadosEmpresa = $request->only([
                'tipo_pessoa',
                'razao_social',
                'nome_fantasia',
                'email',
                'telefone',
                'cpf_cnpj',
                'nicho_id',
            ]);

            $textoParaSlug = $dadosEmpresa['nome_fantasia'] ?? $dadosEmpresa['razao_social'];
            $slugBase = FormatHelper::formatSlug($textoParaSlug);
            $dadosEmpresa['slug'] = $slugBase;
            if (Empresa::where('slug', $dadosEmpresa['slug'])->exists()) {
                do {
                    $dadosEmpresa['slug'] = $slugBase . '-' . Str::random(8);
                } while (Empresa::where('slug', $dadosEmpresa['slug'])->exists());
            }
            $dadosEmpresa['telefone'] = FormatHelper::formatOnlyNumbers($dadosEmpresa['telefone']);

            $empresa = Empresa::create($dadosEmpresa);

            $endereco = null;
            if ($request->has('endereco')) {
                $dadosEndereco = $request->input('endereco');
                $dadosEndereco['empresa_id'] = $empresa->id;
                $endereco = EmpresaEndereco::create($dadosEndereco);
            }

            $dadosUsuario = $request->input('usuario_admin');
            $dadosUsuario['password'] = Hash::make($dadosUsuario['password']);
            $dadosUsuario['telefone'] = $dadosUsuario['telefone'];
            $dadosUsuario['is_master'] = true;
            $dadosUsuario['tipo_cadastro'] = 0;

            $usuario = User::create($dadosUsuario);

            $permissoes = $request->input('usuario_admin.permissoes', []);
            if (count($permissoes) > 0 && is_string(array_keys($permissoes)[0])) {
                $permissoes = array_values($permissoes);
            }
            $permissoesIds = array_map('intval', $permissoes);
            $usuario->permissoes()->sync($permissoesIds);

            $usuario->empresas()->attach($empresa->id);

            if ($endereco) {
                UsuarioEnderecos::create([
                    'usuario_id'       => $usuario->id,
                    'cep'              => $endereco->cep,
                    'rua'              => $endereco->logradouro,
                    'numero'           => $endereco->numero,
                    'complemento'      => $endereco->complemento,
                    'bairro'           => $endereco->bairro,
                    'cidade'           => $endereco->cidade,
                    'estado'           => $endereco->estado,
                    'ponto_referencia' => $endereco->ponto_referencia,
                    'observacoes'      => $endereco->observacoes,
                    'ativo'            => true,
                ]);
            }

            $empresa->configuracoes()->create([
                'empresa_id'             => $empresa->id,
                'faz_entrega'            => false,
                'faz_retirada'           => true,
                'a_combinar'             => false,
                'valor_entrega_padrao'   => 10.00,
                'valor_entrega_minimo'   => 10.00,
            ]);

            $empresa->horarios()->create([
                'empresa_id'     => $empresa->id,
                'dia_semana'     => 'segunda',
                'slug'           => 'segunda',
                'horario_inicio' => '08:00',
                'horario_fim'    => '18:00',
                'padrao'         => true,
            ]);

            DB::commit();

            try {
                $emailService = app(EmailService::class);
                $emailService->sendMailable($usuario->email, new NovoLojistaMail($empresa, $usuario));
            } catch (\Exception $emailException) {
                Log::error('Erro ao enviar email de boas-vindas: ' . $emailException->getMessage());
            }

            return ['ok' => true, 'empresa' => $empresa, 'usuario' => $usuario];
        } catch (\Exception $e) {
            DB::rollBack();

            return ['ok' => false, 'http' => 500, 'body' => ['error' => $e->getMessage()]];
        }
    }
}
