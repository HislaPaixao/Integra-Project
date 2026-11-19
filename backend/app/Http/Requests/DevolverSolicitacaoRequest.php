<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DevolverSolicitacaoRequest
 *
 * Valida POST /api/solicitacoes/{id}/devolver (HU09).
 * Toda solicitação de complementação exige justificativa obrigatória.
 * Quem pode devolver: Médico Regulador ou profissional do NIR.
 */
class DevolverSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario !== null
            && in_array($usuario->perfil, [User::PERFIL_REGULADOR, User::PERFIL_NIR], true);
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Informe o motivo da devolução (quais informações faltam).',
            'motivo.min'      => 'O motivo da devolução deve ter pelo menos 10 caracteres.',
            'motivo.max'      => 'O motivo da devolução deve ter no máximo 2000 caracteres.',
        ];
    }
}
