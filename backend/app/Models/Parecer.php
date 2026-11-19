<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parecer emitido pelo Médico Regulador (3.1.5 Entidade PARECER).
 */
class Parecer extends Model
{
    protected $table = 'pareceres';
    protected $primaryKey = 'id_parecer';

    protected $fillable = [
        'id_solicitacao',
        'id_regulador',
        'texto_parecer',
        'data_parecer',
    ];

    protected function casts(): array
    {
        return [
            'data_parecer' => 'datetime',
        ];
    }

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'id_solicitacao', 'id_solicitacao');
    }

    public function regulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_regulador');
    }
}
