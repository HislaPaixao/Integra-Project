<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formato único de uma solicitação na API (lista e detalhe).
 * Os relacionamentos só aparecem quando foram carregados (with/load).
 */
class SolicitacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id_solicitacao,
            'status'           => $this->status,
            'especialidade'    => $this->especialidade,
            'descricaoClinica' => $this->descricao_clinica,
            'dataSolicitacao'  => $this->data_solicitacao?->toIso8601String(),
            'prioridade'       => $this->whenLoaded('prioridade', fn () => $this->prioridade?->prioridade_final),
            'paciente'         => $this->whenLoaded('paciente', fn () => $this->paciente ? [
                'id'         => $this->paciente->id_paciente,
                'prontuario' => $this->paciente->prontuario,
                'nome'       => $this->paciente->nome,
                'cartaoSus'  => $this->paciente->cartao_sus,
                'leito'      => $this->paciente->leito,
            ] : null),
            'solicitante'      => $this->whenLoaded('solicitante', fn () => $this->solicitante ? [
                'id'   => $this->solicitante->id,
                'nome' => $this->solicitante->name,
                'crm'  => $this->solicitante->crm,
            ] : null),
            'pareceres'        => $this->whenLoaded('pareceres', fn () => $this->pareceres->map(fn ($p) => [
                'id'    => $p->id_parecer,
                'texto' => $p->texto_parecer,
                'data'  => $p->data_parecer?->toIso8601String(),
            ])),
            'agendamento'      => $this->whenLoaded('agendamentos', fn () => ($a = $this->agendamentos->last()) ? [
                'data'  => $a->data_agendamento?->toIso8601String(),
                'local' => $a->local,
            ] : null),
            'historico'        => $this->whenLoaded('historico', fn () => $this->historico->sortBy('data_hora')->values()->map(fn ($h) => [
                'acao'      => $h->acao,
                'descricao' => $h->descricao,
                'dataHora'  => $h->data_hora?->toIso8601String(),
                'usuario'   => $h->id_usuario,
            ])),
        ];
    }
}
