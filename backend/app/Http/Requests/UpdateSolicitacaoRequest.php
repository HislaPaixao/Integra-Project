<?php

namespace App\Http\Requests;

use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateSolicitacaoRequest
 *
 * Valida os dados recebidos em PUT/PATCH /api/solicitacoes/{id}.
 *
 * Regras de acesso:
 *   - só o médico solicitante que criou a solicitação pode editá-la;
 *   - só enquanto ela estiver "Aguarda triagem" ou "Pendente"
 *     (depois de triada/finalizada, o pedido não pode mais ser alterado).
 *
 * Só `especialidade` e `descricao_clinica` podem mudar. Paciente, solicitante
 * e status NÃO são aceitos aqui — por isso `validated()` nunca os devolve.
 */
class UpdateSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario     = $this->user();
        $solicitacao = $this->route('solicitacao');

        if (!$usuario || !$solicitacao instanceof Solicitacao) {
            return false;
        }

        return $usuario->perfil === User::PERFIL_SOLICITANTE
            && (int) $solicitacao->id_solicitante === (int) $usuario->id
            && in_array($solicitacao->status, [
                Solicitacao::STATUS_AGUARDA_TRIAGEM,
                Solicitacao::STATUS_PENDENTE,
            ], true);
    }

    public function rules(): array
    {
        return [
            'especialidade'     => ['sometimes', 'required', 'string', Rule::in(Solicitacao::ESPECIALIDADES)],
            'descricao_clinica' => ['sometimes', 'required', 'string', 'min:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'especialidade.required'     => 'Selecione a clínica destino.',
            'especialidade.in'           => 'Clínica destino inválida. Escolha uma das opções da lista.',
            'descricao_clinica.required' => 'A descrição clínica é obrigatória.',
            'descricao_clinica.min'      => 'A descrição clínica deve ter pelo menos 20 caracteres.',
        ];
    }
}
