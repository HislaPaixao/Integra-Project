<?php

namespace App\Http\Requests;

use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ComplementarSolicitacaoRequest
 *
 * Valida POST /api/solicitacoes/{id}/complementar — o médico solicitante responde
 * ao pedido de informações do regulador (menu "Acompanhamento da solicitação").
 *
 * Regras de acesso: só o solicitante dono da solicitação, e só quando ela está "Pendente".
 */
class ComplementarSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario     = $this->user();
        $solicitacao = $this->route('solicitacao');

        if (!$usuario || !$solicitacao instanceof Solicitacao) {
            return false;
        }

        return $usuario->perfil === User::PERFIL_SOLICITANTE
            && (int) $solicitacao->id_solicitacao > 0
            && (int) $solicitacao->id_solicitante === (int) $usuario->id
            && $solicitacao->status === Solicitacao::STATUS_PENDENTE;
    }

    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'min:10', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'texto.required' => 'Escreva as informações complementares.',
            'texto.min'      => 'As informações complementares devem ter pelo menos 10 caracteres.',
        ];
    }
}
