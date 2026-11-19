<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegulacaoRequest;
use App\Models\Solicitacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class RegulacaoController extends Controller
{
    #[OA\Post(
        path: "/api/solicitacoes/{solicitacao}/regulacao",
        summary: "Regulação do NIR",
        description: "O NIR finaliza uma solicitação 'Triado': conduta 'agendamento' (data e local obrigatórios) ou 'devolucao_parecer'. A solicitação passa a 'Finalizado'.",
        tags: ["Regulação NIR"],
        security: [["sanctum" => []]],
        parameters: [new OA\Parameter(name: "solicitacao", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["conduta", "justificativa"],
                properties: [
                    new OA\Property(property: "conduta", type: "string", enum: ["agendamento", "devolucao_parecer"], example: "agendamento"),
                    new OA\Property(property: "justificativa", type: "string", example: "Vaga disponível."),
                    new OA\Property(property: "data_agendamento", type: "string", format: "date-time", example: "2026-12-10 09:00:00"),
                    new OA\Property(property: "local", type: "string", example: "Ambulatório HUB - sala 4"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Solicitação finalizada."),
            new OA\Response(response: 401, description: "Usuário não autenticado."),
            new OA\Response(response: 403, description: "Apenas o NIR pode regular."),
            new OA\Response(response: 422, description: "Dados inválidos ou solicitação não está 'Triado'.")
        ]
    )]
    public function store(RegulacaoRequest $request, Solicitacao $solicitacao): JsonResponse
    {
        if ($solicitacao->status !== Solicitacao::STATUS_TRIADO) {
            return response()->json(['message' => 'Só é possível regular solicitações com status "Triado".'], 422);
        }

        $dados = $request->validated();

        DB::transaction(function () use ($solicitacao, $request, $dados) {
            if ($dados['conduta'] === RegulacaoRequest::CONDUTA_AGENDAMENTO) {
                $solicitacao->agendamentos()->create([
                    'data_agendamento' => $dados['data_agendamento'],
                    'local'            => $dados['local'],
                    'status'           => 'Agendado',
                ]);
                $descricao = "Agendado para {$dados['data_agendamento']} em {$dados['local']}. {$dados['justificativa']}";
            } else {
                $descricao = "Parecer devolvido ao solicitante. {$dados['justificativa']}";
            }

            $solicitacao->update(['status' => Solicitacao::STATUS_FINALIZADO]);
            $solicitacao->registrarHistorico($request->user(), 'REGULACAO', $descricao);
        });

        return response()->json(['status' => $solicitacao->status, 'conduta' => $dados['conduta']]);
    }
}
