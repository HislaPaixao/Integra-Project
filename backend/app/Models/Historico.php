<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de cada ação feita em uma solicitação (3.1.7 Entidade HISTÓRICO).
 * Garante a rastreabilidade exigida pelo projeto (ex.: motivo de uma devolução).
 */
class Historico extends Model
{
    protected $table = 'historicos';
    protected $primaryKey = 'id_historico';

    // A tabela só tem `data_hora`, sem created_at/updated_at.
    public $timestamps = false;

    protected $fillable = [
        'id_solicitacao',
        'id_usuario',
        'acao',
        'descricao',
        'data_hora',
    ];

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
        ];
    }

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'id_solicitacao', 'id_solicitacao');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
