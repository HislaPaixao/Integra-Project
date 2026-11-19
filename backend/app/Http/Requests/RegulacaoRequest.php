<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RegulacaoRequest
 *
 * Valida POST /api/solicitacoes/{id}/regulacao — o pop-up de regulação do NIR:
 *   - conduta: "agendamento" (marca a consulta) ou "devolucao_parecer" (devolve o parecer
 *     ao médico solicitante, usado nos casos P3 / atenção básica);
 *   - justificativa (texto);
 *   - data e local — obrigatórios quando a conduta é "agendamento".
 *
 * Só o profissional do NIR pode regular.
 */
class RegulacaoRequest extends FormRequest
{
    public const CONDUTA_AGENDAMENTO       = 'agendamento';
    public const CONDUTA_DEVOLUCAO_PARECER = 'devolucao_parecer';

    public function authorize(): bool
    {
        return $this->user()?->perfil === User::PERFIL_NIR;
    }

    public function rules(): array
    {
        $agendamento = fn () => $this->input('conduta') === self::CONDUTA_AGENDAMENTO;

        return [
            'conduta'          => ['required', Rule::in([self::CONDUTA_AGENDAMENTO, self::CONDUTA_DEVOLUCAO_PARECER])],
            'justificativa'    => ['required', 'string', 'min:5', 'max:2000'],
            'data_agendamento' => [Rule::requiredIf($agendamento), 'nullable', 'date', 'after:now'],
            'local'            => [Rule::requiredIf($agendamento), 'nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'conduta.required'          => 'Escolha a conduta: agendamento ou devolução do parecer.',
            'conduta.in'                => 'Conduta inválida.',
            'justificativa.required'    => 'Informe a justificativa.',
            'justificativa.min'         => 'A justificativa deve ter pelo menos 5 caracteres.',
            'data_agendamento.required' => 'Informe a data e o horário do agendamento.',
            'data_agendamento.after'    => 'A data do agendamento deve ser futura.',
            'local.required'            => 'Informe o local do atendimento.',
        ];
    }
}
