<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParecerRequest;
use App\Models\Parecer;
use App\Models\Prioridade;
use App\Models\Solicitacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ParecerController extends Controller
{
    #[OA\Post(
        path: "/api/pareceres",
        summary: "Registrar parecer médico (triagem)",
        description: "O Médico Regulador emite o parecer. Status 'Triado' exige prioridade P1/P2/P3; status 'Pendente' devolve ao solicitante para complementar. Tudo fica no histórico.",
        tags: ["Pareceres"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["id_solicitacao", "texto_parecer", "status"],
                properties: [
                    new OA\Property(property: "id_solicitacao", type: "integer", example: 1),
                    new OA\Property(property: "texto_parecer", type: "string", example: "Caso com indicação de avaliação especializada."),
                    new OA\Property(property: "status", type: "string", enum: ["Triado", "Pendente"], example: "Triado"),
                    new OA\Property(property: "prioridade", type: "string", enum: ["P1", "P2", "P3"], example: "P2"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Parecer registrado."),
            new OA\Response(response: 401, description: "Usuário não autenticado."),
            new OA\Response(response: 403, description: "Apenas o Médico Regulador pode emitir parecer."),
            new OA\Response(response: 422, description: "Dados inválidos ou solicitação já finalizada.")
        ]
    )]
    public function store(StoreParecerRequest $request): JsonResponse
    {
        $solicitacao = Solicitacao::findOrFail($request->id_solicitacao);

        if ($solicitacao->status === Solicitacao::STATUS_FINALIZADO) {
            return response()->json(['message' => 'Solicitação já finalizada não pode receber parecer.'], 422);
        }

        $status = $request->validated('status');
        $texto  = $request->validated('texto_parecer');
        $prio   = $request->validated('prioridade');

        $parecer = DB::transaction(function () use ($solicitacao, $request, $status, $texto, $prio) {
            $parecer = Parecer::create([
                'id_solicitacao' => $solicitacao->id_solicitacao,
                'id_regulador'   => $request->user()->id,
                'texto_parecer'  => $texto,
                'data_parecer'   => now(),
            ]);

            $solicitacao->update(['status' => $status]);

            if ($status === Solicitacao::STATUS_TRIADO) {
                Prioridade::updateOrCreate(
                    ['id_solicitacao' => $solicitacao->id_solicitacao],
                    ['id_regulador' => $request->user()->id, 'prioridade_final' => $prio, 'justificativa' => $texto]
                );
                $solicitacao->registrarHistorico($request->user(), 'TRIAGEM', "Prioridade {$prio}. {$texto}");
            } else {
                $solicitacao->registrarHistorico($request->user(), 'PENDENCIA', $texto);
            }

            return $parecer;
        });

        return response()->json([
            'id'      => $parecer->id_parecer,
            'status'  => $solicitacao->status,
            'message' => 'Parecer registrado com sucesso.',
        ], 201);
    }
}
