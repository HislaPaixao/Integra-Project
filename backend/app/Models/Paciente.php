<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cópia local dos dados do paciente trazidos do AGHU (3.1.2 Entidade PACIENTE).
 * O AGHU continua sendo a fonte da verdade; esta tabela existe para que a
 * solicitação possa referenciar o paciente.
 */
class Paciente extends Model
{
    protected $table = 'pacientes';
    protected $primaryKey = 'id_paciente';

    protected $fillable = [
        'prontuario',
        'nome',
        'cpf',
        'cartao_sus',
        'leito',
    ];

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(Solicitacao::class, 'id_paciente', 'id_paciente');
    }
}
