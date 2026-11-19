<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pedido de consultoria feito pelo médico solicitante (3.1.3 Entidade SOLICITAÇÃO).
 *
 * Status do processo (conforme o fluxo "Parecer Interno Ambulatorial"):
 *
 *   Aguarda triagem ──(regulador)──► Triado ──(NIR)──► Finalizado
 *          ▲                  │
 *          │                  └─────► Pendente ──(solicitante complementa)──┐
 *          └───────────────────────────────────────────────────────────────┘
 */
class Solicitacao extends Model
{
    public const STATUS_AGUARDA_TRIAGEM = 'Aguarda triagem';
    public const STATUS_TRIADO          = 'Triado';
    public const STATUS_PENDENTE        = 'Pendente';
    public const STATUS_FINALIZADO      = 'Finalizado';

    public const STATUS = [
        self::STATUS_AGUARDA_TRIAGEM,
        self::STATUS_TRIADO,
        self::STATUS_PENDENTE,
        self::STATUS_FINALIZADO,
    ];

    public const PRIORIDADES = ['P1', 'P2', 'P3'];

    /** Clínicas destino disponíveis (lista definida no documento — regra de negócio 2). */
    public const ESPECIALIDADES = [
        'Ginecologia cirúrgica',
        'Cirurgia geral',
        'Endocrinologia adulto',
        'Endocrinologia pediátrica',
        'Gastroenterologia',
        'Pneumologia',
        'Proctologia',
        'Urologia',
        'Fisioterapia',
        'Nutrição',
        'Fonoaudiologia',
        'Terapia ocupacional',
    ];

    protected $table = 'solicitacoes';
    protected $primaryKey = 'id_solicitacao';

    protected $fillable = [
        'id_paciente',
        'id_solicitante',
        'especialidade',
        'descricao_clinica',
        'status',
        'data_solicitacao',
    ];

    protected function casts(): array
    {
        return [
            'data_solicitacao' => 'datetime',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente', 'id_paciente');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_solicitante');
    }

    public function pareceres(): HasMany
    {
        return $this->hasMany(Parecer::class, 'id_solicitacao', 'id_solicitacao');
    }

    public function prioridade(): HasOne
    {
        return $this->hasOne(Prioridade::class, 'id_solicitacao', 'id_solicitacao');
    }

    public function historico(): HasMany
    {
        return $this->hasMany(Historico::class, 'id_solicitacao', 'id_solicitacao');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'id_solicitacao', 'id_solicitacao');
    }

    /**
     * Registra no histórico uma ação feita por um usuário nesta solicitação
     * (rastreabilidade exigida pelo projeto).
     */
    public function registrarHistorico(User|int $usuario, string $acao, ?string $descricao = null): Historico
    {
        return $this->historico()->create([
            'id_usuario' => $usuario instanceof User ? $usuario->id : $usuario,
            'acao'       => $acao,
            'descricao'  => $descricao,
            'data_hora'  => now(),
        ]);
    }
}
