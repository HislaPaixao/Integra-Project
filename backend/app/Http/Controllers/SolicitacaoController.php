<?php

namespace App\Http\Controllers;

use App\Models\Solicitacao;
use App\Http\Requests\DevolverSolicitacaoRequest;
use App\Http\Requests\StoreSolicitacaoRequest;
use App\Http\Requests\UpdateSolicitacaoRequest;
use App\Http\Requests\ComplementarSolicitacaoRequest;
use App\Http\Resources\SolicitacaoResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

/**
 * SolicitacaoController
 *
 * Gerencia o ciclo de vida das solicitações de consultoria ambulatorial.
 *
 * Endpoints documentados (3.5 Principais Endpoints):
 *   GET  /api/solicitacoes          — lista para triagem do Médico Regulador
 *   GET  /api/solicitacoes/{id}     — detalhe de uma solicitação
 *   POST /api/solicitacoes          — cria nova solicitação (HU02)
 *   PUT  /api/solicitacoes/{id}     — atualiza solicitação existente
 *   POST /api/solicitacoes/{id}/devolver — devolve para complementação (HU09)
 */
class SolicitacaoController extends Controller
{
    /**
     * GET /api/solicitacoes
     * Lista solicitações aguardando triagem pelo Médico Regulador (HU04).
     */

#[OA\Get(
    path: "/api/solicitacoes",
    summary: "Listar solicitações",
    description: "Lista as solicitações de consultoria para triagem.",
    tags: ["Solicitações"],
    security: [["sanctum" => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: "Lista de solicitações retornada com sucesso."
        ),
        new OA\Response(
            response: 401,
            description: "Usuário não autenticado."
        )
    ]
)]

    public function index(Request $request)
    {
        $usuario = $request->user();
        $query = Solicitacao::with(['paciente', 'solicitante', 'prioridade'])
            ->orderBy('data_solicitacao', 'asc');

        // Médico solicitante só enxerga as próprias solicitações.
        if ($usuario->perfil === User::PERFIL_SOLICITANTE) {
            $query->where('id_solicitante', $usuario->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('especialidade')) {
            $query->where('especialidade', $request->query('especialidade'));
        }
        if ($request->filled('prioridade')) {
            $prio = $request->query('prioridade');
            $query->whereHas('prioridade', fn ($q) => $q->where('prioridade_final', $prio));
        }
        if ($request->filled('busca')) {
            $termo = '%' . mb_strtolower($request->query('busca')) . '%';
            $query->whereHas('paciente', fn ($q) => $q
                ->whereRaw('LOWER(nome) LIKE ?', [$termo])
                ->orWhereRaw('LOWER(prontuario) LIKE ?', [$termo]));
        }

        return SolicitacaoResource::collection($query->get());
    }


#[OA\Get(
    path: "/api/solicitacoes/{solicitacao}",
    summary: "Consultar solicitação",
    description: "Retorna os detalhes de uma solicitação específica.",
    tags: ["Solicitações"],
    security: [["sanctum" => []]],
    parameters: [
        new OA\Parameter(
            name: "solicitacao",
            in: "path",
            required: true,
            description: "Identificador da solicitação.",
            schema: new OA\Schema(type: "integer"),
            example: 1
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: "Solicitação encontrada."
        ),
        new OA\Response(
            response: 401,
            description: "Usuário não autenticado."
        ),
        new OA\Response(
            response: 404,
            description: "Solicitação não encontrada."
        )
    ]
)]

    public function show(Request $request, Solicitacao $solicitacao)
    {
        $usuario = $request->user();
        if ($usuario->perfil === User::PERFIL_SOLICITANTE
            && (int) $solicitacao->id_solicitante !== (int) $usuario->id) {
            return response()->json(['message' => 'Você não tem permissão para ver esta solicitação.'], 403);
        }

        $solicitacao->load(['paciente', 'solicitante', 'pareceres', 'prioridade', 'historico', 'agendamentos']);
        return new SolicitacaoResource($solicitacao);
    }


#[OA\Post(
    path: "/api/solicitacoes",
    summary: "Cadastrar solicitação",
    description: "Registra uma nova solicitação de consultoria ambulatorial.",
    tags: ["Solicitações"],
    security: [["sanctum" => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["id_paciente", "especialidade", "descricao_clinica"],
            properties: [
                new OA\Property(
                    property: "id_paciente",
                    type: "integer",
                    example: 1
                ),
                new OA\Property(
                    property: "especialidade",
                    type: "string",
                    maxLength: 100,
                    example: "Endocrinologia adulto"
                ),
                new OA\Property(
                    property: "descricao_clinica",
                    type: "string",
                    minLength: 20,
                    example: "Paciente com diabetes tipo 2."
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: "Solicitação cadastrada com sucesso."
        ),
        new OA\Response(
            response: 401,
            description: "Usuário não autenticado ou sem autorização."
        ),
        new OA\Response(
            response: 422,
            description: "Dados enviados inválidos."
        )
    ]
)]

    public function store(StoreSolicitacaoRequest $request): JsonResponse
    {
        $solicitacao = Solicitacao::create([
            'id_paciente'      => $request->id_paciente,
            'id_solicitante'   => auth()->id(),   // vem do login — não do form
            'especialidade'    => $request->especialidade,
            'descricao_clinica'=> $request->descricao_clinica,
            'status'           => Solicitacao::STATUS_AGUARDA_TRIAGEM,
            'data_solicitacao' => now(),
        ]);

        $solicitacao->registrarHistorico($request->user(), 'CRIACAO', 'Solicitação criada.');

        return response()->json([
            'id'     => $solicitacao->id_solicitacao,
            'status' => $solicitacao->status,
        ], 201);
    }

    /**
     * PUT /api/solicitacoes/{id}
     * Atualiza uma solicitação existente.
     */
#[OA\Put(
    path: "/api/solicitacoes/{solicitacao}",
    summary: "Atualizar solicitação",
    description: "Atualiza os dados de uma solicitação existente.",
    tags: ["Solicitações"],
    security: [["sanctum" => []]],
    parameters: [
        new OA\Parameter(
            name: "solicitacao",
            in: "path",
            required: true,
            description: "Identificador da solicitação.",
            schema: new OA\Schema(type: "integer"),
            example: 1
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "especialidade", type: "string", example: "Cardiologia"),
                new OA\Property(property: "descricao_clinica", type: "string", example: "Descrição clínica atualizada.")
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Solicitação atualizada."),
        new OA\Response(response: 401, description: "Usuário não autenticado."),
        new OA\Response(response: 404, description: "Solicitação não encontrada."),
        new OA\Response(response: 422, description: "Dados inválidos.")
    ]
)]
#[OA\Patch(
    path: "/api/solicitacoes/{solicitacao}",
    summary: "Atualizar parcialmente solicitação",
    description: "Atualiza parcialmente os dados de uma solicitação.",
    tags: ["Solicitações"],
    security: [["sanctum" => []]],
    parameters: [
        new OA\Parameter(
            name: "solicitacao",
            in: "path",
            required: true,
            schema: new OA\Schema(type: "integer"),
            example: 1
        )
    ],
    responses: [
        new OA\Response(response: 200, description: "Solicitação atualizada."),
        new OA\Response(response: 401, description: "Usuário não autenticado."),
        new OA\Response(response: 404, description: "Solicitação não encontrada.")
    ]
)]

    public function update(UpdateSolicitacaoRequest $request, Solicitacao $solicitacao): JsonResponse
    {
        $solicitacao->update($request->validated());
        $solicitacao->registrarHistorico($request->user(), 'EDICAO', 'Solicitação editada pelo solicitante.');
        return response()->json(new SolicitacaoResource($solicitacao->load(['paciente'])));
    }

    /**
     * POST /api/solicitacoes/{id}/devolver
     * Devolve a solicitação ao médico solicitante para complementação (HU09).
     *
     * O motivo é obrigatório e fica registrado no histórico da solicitação.
     * Só Médico Regulador ou NIR podem devolver; solicitação já finalizada não volta.
     *
     * Requisição: { "motivo": "Informar tipo de insulina utilizada." }
     * Resposta:   { "status": "Pendente", "motivo": "Informar tipo de insulina utilizada." }
     */

#[OA\Post(
    path: "/api/solicitacoes/{solicitacao}/devolver",
    summary: "Devolver solicitação",
    description: "Devolve a solicitação ao médico solicitante para complementação (status Pendente). O motivo é obrigatório e fica registrado no histórico.",
    tags: ["Solicitações"],
    security: [["sanctum" => []]],
    parameters: [
        new OA\Parameter(
            name: "solicitacao",
            in: "path",
            required: true,
            schema: new OA\Schema(type: "integer"),
            example: 1
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["motivo"],
            properties: [
                new OA\Property(
                    property: "motivo",
                    type: "string",
                    minLength: 10,
                    maxLength: 2000,
                    example: "Informar tipo de insulina utilizada."
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: "Solicitação devolvida.",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "status", type: "string", example: "Pendente"),
                    new OA\Property(property: "motivo", type: "string", example: "Informar tipo de insulina utilizada.")
                ]
            )
        ),
        new OA\Response(response: 401, description: "Usuário não autenticado."),
        new OA\Response(response: 403, description: "Perfil sem permissão para devolver."),
        new OA\Response(response: 404, description: "Solicitação não encontrada."),
        new OA\Response(response: 422, description: "Motivo ausente/inválido ou solicitação já finalizada.")
    ]
)]

    public function devolver(DevolverSolicitacaoRequest $request, Solicitacao $solicitacao): JsonResponse
    {
        if ($solicitacao->status === Solicitacao::STATUS_FINALIZADO) {
            return response()->json(
                ['message' => 'Solicitação já finalizada não pode ser devolvida.'],
                422
            );
        }

        $motivo = $request->validated('motivo');

        // Status e histórico mudam juntos: se um falhar, nenhum dos dois é gravado.
        DB::transaction(function () use ($solicitacao, $request, $motivo) {
            $solicitacao->update(['status' => Solicitacao::STATUS_PENDENTE]);

            $solicitacao->historico()->create([
                'id_usuario' => $request->user()->id,
                'acao'       => 'DEVOLUCAO',
                'descricao'  => $motivo,
                'data_hora'  => now(),
            ]);
        });

        return response()->json([
            'status' => $solicitacao->status,
            'motivo' => $motivo,
        ]);
    }

#[OA\Post(
    path: "/api/solicitacoes/{solicitacao}/complementar",
    summary: "Complementar solicitação pendente",
    description: "O médico solicitante envia as informações pedidas pelo regulador. A solicitação volta para 'Aguarda triagem' e o texto fica no histórico.",
    tags: ["Solicitações"],
    security: [["sanctum" => []]],
    parameters: [new OA\Parameter(name: "solicitacao", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["texto"],
            properties: [new OA\Property(property: "texto", type: "string", minLength: 10, example: "Paciente usa insulina NPH 20UI.")]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: "Informações enviadas; solicitação volta para triagem."),
        new OA\Response(response: 401, description: "Usuário não autenticado."),
        new OA\Response(response: 403, description: "Sem permissão (não é o solicitante ou a solicitação não está pendente)."),
        new OA\Response(response: 422, description: "Texto inválido.")
    ]
)]
    public function complementar(ComplementarSolicitacaoRequest $request, Solicitacao $solicitacao): JsonResponse
    {
        $texto = $request->validated('texto');

        DB::transaction(function () use ($solicitacao, $request, $texto) {
            $solicitacao->update(['status' => Solicitacao::STATUS_AGUARDA_TRIAGEM]);
            $solicitacao->registrarHistorico($request->user(), 'COMPLEMENTO', $texto);
        });

        return response()->json(['status' => $solicitacao->status]);
    }
}
