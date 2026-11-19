<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agendamento feito pelo NIR após a análise (3.1.6 Entidade AGENDAMENTO).
 */
class Agendamento extends Model
{
    protected $table = 'agendamentos';
    protected $primaryKey = 'id_agendamento';

    protected $fillable = [
        'id_solicitacao',
        'data_agendamento',
        'local',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data_agendamento' => 'datetime',
        ];
    }

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'id_solicitacao', 'id_solicitacao');
    }
}
