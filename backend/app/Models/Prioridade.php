<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sugestão de prioridade da IA e decisão final do regulador (3.1.4 Entidade PRIORIDADE).
 * A IA apenas sugere; a decisão final é sempre do Médico Regulador.
 */
class Prioridade extends Model
{
    protected $table = 'prioridades';
    protected $primaryKey = 'id_prioridade';

    protected $fillable = [
        'id_solicitacao',
        'id_regulador',
        'prioridade_ia',
        'prioridade_final',
        'justificativa',
    ];

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'id_solicitacao', 'id_solicitacao');
    }

    public function regulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_regulador');
    }
}
