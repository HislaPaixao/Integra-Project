<?php

namespace App\Http\Requests;

use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreSolicitacaoRequest
 *
 * Valida os dados recebidos no POST /api/solicitacoes (HU02).
 *
 * Campos obrigatórios conforme documentação (seção 2.1.1 — HU02):
 *   - id_paciente       → paciente identificado via AGHU (valor `idPaciente` devolvido por GET /api/pacientes/{prontuario})
 *   - especialidade     → clínica destino selecionada no formulário (uma das 12 da lista)
 *   - descricao_clinica → descrição da situação clínica (obrigatória, HU02 critério 2)
 *
 * O id_solicitante NÃO é recebido do formulário — é extraído do token autenticado
 * no controller, impedindo falsificação de identidade.
 */
class StoreSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Apenas usuários autenticados com perfil "solicitante" podem criar solicitações
        return $this->user()?->perfil === User::PERFIL_SOLICITANTE;
    }

    public function rules(): array
    {
        return [
            // O paciente precisa existir no banco (é gravado quando a busca no AGHU o encontra).
            'id_paciente'       => ['required', 'integer', 'exists:pacientes,id_paciente'],
            'especialidade'     => ['required', 'string', Rule::in(Solicitacao::ESPECIALIDADES)],
            'descricao_clinica' => ['required', 'string', 'min:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_paciente.required'       => 'O paciente é obrigatório.',
            'id_paciente.exists'         => 'Paciente não encontrado no sistema.',
            'especialidade.required'     => 'Selecione a clínica destino.',
            'especialidade.in'           => 'Clínica destino inválida. Escolha uma das opções da lista.',
            'descricao_clinica.required' => 'A descrição clínica é obrigatória.',
            'descricao_clinica.min'      => 'A descrição clínica deve ter pelo menos 20 caracteres.',
        ];
    }
}
