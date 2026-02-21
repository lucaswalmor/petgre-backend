<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresas';

    protected $fillable = [
        'tipo_pessoa',
        'razao_social',
        'nome_fantasia',
        'slug',
        'email',
        'telefone',
        'cpf_cnpj',
        'path_logo',
        'path_banner',
        'nicho_id',
        'cadastro_completo',
        'ativo',
    ];

    // Relação com nicho
    public function nicho()
    {
        return $this->belongsTo(NichosEmpresa::class, 'nicho_id');
    }

    // Relação com endereço
    public function endereco()
    {
        return $this->hasOne(EmpresaEndereco::class, 'empresa_id');
    }

    // Relação com configurações
    public function configuracoes()
    {
        return $this->hasOne(EmpresaConfiguracoes::class, 'empresa_id');
    }

    // Relação com horários
    public function horarios()
    {
        return $this->hasMany(EmpresaHorarios::class, 'empresa_id');
    }

    // Relação com bairros de entrega
    public function bairrosEntregas()
    {
        return $this->hasMany(EmpresaBairrosEntregas::class, 'empresa_id');
    }

    // Relação com assinatura
    public function assinatura()
    {
        return $this->hasOne(EmpresaAssinatura::class, 'empresa_id');
    }

    // Relação com formas de pagamento
    public function formasPagamentos()
    {
        return $this->hasMany(EmpresaFormasPagamentos::class, 'empresa_id');
    }

    // Relação com usuários
    public function usuarios()
    {
        return $this->hasMany(UsuarioEmpresas::class, 'empresa_id');
    }

    // Relação com favoritos
    public function empresaFavoritos()
    {
        return $this->hasMany(EmpresaFavorito::class, 'empresa_id');
    }

    // Relação com produtos
    public function produtos()
    {
        return $this->hasMany(Produto::class, 'empresa_id');
    }

    // Relação com pedidos
    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'empresa_id');
    }

    // Relação com avaliações
    public function avaliacoes()
    {
        return $this->hasMany(EmpresaAvaliacao::class, 'empresa_id');
    }

    // Relação com cupons da empresa
    public function cupons()
    {
        return $this->hasMany(EmpresaCupom::class, 'empresa_id');
    }

    // Relação com pausas agendadas
    public function pausasAgendadas()
    {
        return $this->hasMany(EmpresaPausaAgendada::class, 'empresa_id');
    }

    /**
     * Calcular média das avaliações da empresa
     */
    public function calcularMediaAvaliacoes()
    {
        return $this->avaliacoes()->selectRaw('AVG(nota) as media, COUNT(*) as total')->first();
    }

    /**
     * Obter cupons ativos da empresa
     */
    public function cuponsAtivos()
    {
        return $this->cupons()->ativos()->get();
    }

    /**
     * Verificar se a empresa está aberta no momento (America/Sao_Paulo).
     * Considera horário de funcionamento e pausas agendadas (em pausa = fechada).
     */
    public function isAberta(): bool
    {
        if (!$this->relationLoaded('horarios')) {
            return false;
        }

        $tz = 'America/Sao_Paulo';
        $agora = Carbon::now($tz);
        $diaSemanaIngles = strtolower($agora->format('l'));

        $mapaDias = [
            'monday' => 'segunda',
            'tuesday' => 'terca',
            'wednesday' => 'quarta',
            'thursday' => 'quinta',
            'friday' => 'sexta',
            'saturday' => 'sabado',
            'sunday' => 'domingo',
        ];

        $diaSemana = $mapaDias[$diaSemanaIngles] ?? null;
        $horaAtual = $agora->format('H:i:s');

        if (!$diaSemana) {
            return false;
        }

        $dentroHorario = false;
        foreach ($this->horarios as $horario) {
            if ($horario->dia_semana === $diaSemana) {
                if ($horaAtual >= $horario->horario_inicio && $horaAtual <= $horario->horario_fim) {
                    $dentroHorario = true;
                    break;
                }
            }
        }

        if (!$dentroHorario) {
            return false;
        }

        return $this->getPausaAtual($tz, $agora, $horaAtual) === null;
    }

    /**
     * Retorna a pausa agendada em que a empresa está no momento (ou null).
     * Só considera se estiver dentro do horário de funcionamento.
     */
    private function getPausaAtual(string $tz, Carbon $agora, string $horaAtual): ?EmpresaPausaAgendada
    {
        if (!$this->relationLoaded('pausasAgendadas')) {
            $this->load('pausasAgendadas');
        }
        foreach ($this->pausasAgendadas as $pausa) {
            $rawInicio = $pausa->getRawOriginal('data_inicio');
            $rawFim = $pausa->getRawOriginal('data_fim');
            $inicio = Carbon::parse($rawInicio, $tz);
            $fim = Carbon::parse($rawFim, $tz);
            if ($pausa->recorrente) {
                if ($horaAtual >= $inicio->format('H:i:s') && $horaAtual <= $fim->format('H:i:s')) {
                    return $pausa;
                }
            } else {
                if ($agora->between($inicio, $fim)) {
                    return $pausa;
                }
            }
        }
        return null;
    }

    /**
     * Quando a loja está fechada por pausa agendada, retorna o horário em que volta (fim da pausa).
     * Formato: "16:00" (hoje), "amanhã 16:00" ou "dd/mm 16:00". Retorna null se aberta ou fechada por horário.
     */
    public function getFechadoAte(): ?string
    {
        if (!$this->relationLoaded('horarios')) {
            return null;
        }
        $tz = 'America/Sao_Paulo';
        $agora = Carbon::now($tz);
        $diaSemanaIngles = strtolower($agora->format('l'));
        $mapaDias = [
            'monday' => 'segunda', 'tuesday' => 'terca', 'wednesday' => 'quarta', 'thursday' => 'quinta',
            'friday' => 'sexta', 'saturday' => 'sabado', 'sunday' => 'domingo',
        ];
        $diaSemana = $mapaDias[$diaSemanaIngles] ?? null;
        $horaAtual = $agora->format('H:i:s');
        if (!$diaSemana) {
            return null;
        }
        $dentroHorario = false;
        foreach ($this->horarios as $horario) {
            if ($horario->dia_semana === $diaSemana && $horaAtual >= $horario->horario_inicio && $horaAtual <= $horario->horario_fim) {
                $dentroHorario = true;
                break;
            }
        }
        if (!$dentroHorario) {
            return null;
        }
        $pausa = $this->getPausaAtual($tz, $agora, $horaAtual);
        if ($pausa === null) {
            return null;
        }
        $rawFim = $pausa->getRawOriginal('data_fim');
        $fim = Carbon::parse($rawFim, $tz);
        $hoje = $agora->toDateString();
        $amanha = $agora->copy()->addDay()->toDateString();
        if ($fim->toDateString() === $hoje) {
            return $fim->format('H:i');
        }
        if ($fim->toDateString() === $amanha) {
            return 'amanhã ' . $fim->format('H:i');
        }
        return $fim->format('d/m') . ' ' . $fim->format('H:i');
    }
}
