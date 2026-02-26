<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    protected $table = 'usuarios';
    
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'email',
        'password',
        'telefone',
        'ativo',
        'is_master',
        'desenvolvedor',
        'tipo_cadastro',
        'primeiro_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'primeiro_login' => 'boolean',
            'desenvolvedor' => 'boolean',
        ];
    }

    // Relação com endereços
    public function enderecos()
    {
        return $this->hasMany(UsuarioEnderecos::class, 'usuario_id');
    }

    // Relação com empresas (através da tabela usuarios_empresas)
    public function empresas()
    {
        return $this->belongsToMany(Empresa::class, 'usuarios_empresas', 'usuario_id', 'empresa_id');
    }

    // Relação com pedidos
    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'usuario_id');
    }

    // Relação com empresas (através da tabela usuario_empresas)
    public function usuarioEmpresas()
    {
        return $this->hasMany(UsuarioEmpresas::class, 'usuario_id');
    }

    // Relação com empresas favoritas
    public function empresaFavoritos()
    {
        return $this->hasMany(EmpresaFavorito::class, 'usuario_id');
    }

    // Relação many-to-many com permissões
    public function permissoes()
    {
        return $this->belongsToMany(Permissao::class, 'usuarios_permissoes', 'usuario_id', 'permissao_id');
    }

    // Verifica se o usuário tem uma permissão específica
    public function hasPermission(string $slug): bool
    {
        // Se é master, pode tudo
        if ($this->isMaster()) {
            return true;
        }

        return $this->permissoes()->where('slug', $slug)->exists();
    }

    // Verifica se o usuário tem qualquer uma das permissões
    public function hasAnyPermission(array $slugs): bool
    {
        // Se é master, pode tudo
        if ($this->isMaster()) {
            return true;
        }

        return $this->permissoes()->whereIn('slug', $slugs)->exists();
    }

    // Verifica se o usuário é master da empresa
    public function isMaster()
    {
        return $this->is_master;
    }

    // Verifica se o usuário é lojista (tipo_cadastro = 0)
    public function isLojista()
    {
        return $this->tipo_cadastro === 0;
    }

    // Verifica se o usuário é cliente (tipo_cadastro = 1)
    public function isCliente()
    {
        return $this->tipo_cadastro === 1;
    }

    /**
     * Retorna a primeira empresa ativa do usuário (para lojistas).
     */
    public function empresaUsuarioAtivo()
    {
        return $this->empresas()->where('ativo', true)->first();
    }

    // Relação com avaliações feitas pelo usuário
    public function avaliacoes()
    {
        return $this->hasMany(EmpresaAvaliacao::class, 'usuario_id');
    }

    // Relação com cupons usados pelo usuário (empresa)
    public function empresaCuponsUsados()
    {
        return $this->hasMany(EmpresaCupomUsado::class, 'usuario_id');
    }

    // Relação com cupons do sistema usados pelo usuário
    public function sistemaCuponsUsados()
    {
        return $this->hasMany(SistemaCupomUsado::class, 'usuario_id');
    }

    // Relação com cupons atribuídos ao usuário
    public function cupons()
    {
        return $this->hasMany(UsuarioCupom::class, 'usuario_id');
    }

    /**
     * Obter cupons válidos do usuário
     */
    public function cuponsValidos()
    {
        return $this->cupons()->validos()->with('cupom')->get();
    }
}
