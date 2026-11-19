<?php

namespace App\Http\Requests;

use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreParecerRequest
 *
 * Valida POST /api/pareceres — o pop-up de regulação do Médico Regulador:
 *   - texto do parecer;
 *   - status: "Triado" (avaliação concluída) ou "Pendente" (falta informação);
 *   - prioridade P1/P2/P3 — obrigatória quando o status é "Triado".
 *
 * Só o Médico Regulador pode emitir parecer.
 */
class StoreParecerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->perfil === User::PERFIL_REGULADOR;
    }

    public function rules(): array
    {
        return [
            'id_solicitacao' => ['required', 'integer', 'exists:solicitacoes,id_solicitacao'],
            'texto_parecer'  => ['required', 'string', 'min:10'],
            'status'         => ['required', Rule::in([Solicitacao::STATUS_TRIADO, Solicitacao::STATUS_PENDENTE])],
            'prioridade'     => [
                Rule::requiredIf(fn () => $this->input('status') === Solicitacao::STATUS_TRIADO),
                'nullable',
                Rule::in(Solicitacao::PRIORIDADES),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id_solicitacao.required' => 'A solicitação é obrigatória.',
            'id_solicitacao.exists'   => 'Solicitação não encontrada.',
            'texto_parecer.required'  => 'O texto do parecer é obrigatório.',
            'texto_parecer.min'       => 'O parecer deve ter pelo menos 10 caracteres.',
            'status.required'         => 'Informe o status: Triado ou Pendente.',
            'status.in'               => 'O status deve ser "Triado" ou "Pendente".',
            'prioridade.required'     => 'A prioridade (P1, P2 ou P3) é obrigatória quando o status é "Triado".',
            'prioridade.in'           => 'A prioridade deve ser P1, P2 ou P3.',
        ];
    }
}
