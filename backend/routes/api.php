<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PacienteController;
use App\Http\Controllers\SolicitacaoController;
use App\Http\Controllers\ParecerController;
use App\Http\Controllers\RegulacaoController;

/*
|--------------------------------------------------------------------------
| API Routes — Integra / NIR
| Módulo de Consultoria Ambulatorial — TakeOff RTIV
|--------------------------------------------------------------------------
|
| Todas as rotas abaixo são protegidas pelo middleware 'auth:sanctum',
| garantindo que apenas usuários autenticados possam acessar o módulo.
| (HU01 — "ele só poderá acessar esse módulo se estiver logado")
|
*/

// ─── Autenticação ────────────────────────────────────────────────────────────
// Rota pública: login (não exige token)
Route::post('/auth/login',  [\App\Http\Controllers\Auth\AuthController::class, 'login']);

// Rotas protegidas por token JWT (Sanctum)
Route::middleware('auth:sanctum')->group(function () {

    // Retorna os dados do usuário logado (nome, CRM, perfil)
    // Usado pelo AuthService do Angular para pré-preencher nome e CRM (HU01)
    Route::get('/auth/me', function (Request $request) {
        $u = $request->user();
        return response()->json(['id' => $u->id, 'nome' => $u->name, 'email' => $u->email, 'crm' => $u->crm, 'perfil' => $u->perfil]);
    });

    Route::post('/auth/logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout']);

    // ─── ÉPICO 1: Solicitação de Consultoria ─────────────────────────────────

    /**
     * GET /api/pacientes/{prontuario}
     * Busca os dados do paciente no AGHU pelo número de prontuário.
     * Resposta: { prontuario, nomePaciente, cartaoSus, cpf, leito, numeroConsulta, clinicaOrigem }
     * Equipe 1 — HU01
     */
    Route::get('/pacientes/{prontuario}', [PacienteController::class, 'show']);

    /**
     * GET    /api/solicitacoes        → lista para triagem (HU04 — Equipe 2)
     * GET    /api/solicitacoes/{id}   → detalhe de uma solicitação
     * POST   /api/solicitacoes        → cria nova solicitação (HU02 — Equipe 1)
     * PUT    /api/solicitacoes/{id}   → atualiza solicitação
     */
    // ->parameters(): sem isso o Laravel "singulariza" solicitacoes → {solicitaco}, que não
    // combina com `Solicitacao $solicitacao` nos controllers. Resultado: show/update recebiam
    // um registro vazio em vez da solicitação do banco.
    Route::apiResource('solicitacoes', SolicitacaoController::class)
        ->except(['destroy'])
        ->parameters(['solicitacoes' => 'solicitacao']);

    /**
     * POST /api/solicitacoes/{id}/devolver
     * Devolve a solicitação para complementação de informações (HU09).
     * Altera o status para "Pendente" e registra o motivo no histórico.
     */
    Route::post('/solicitacoes/{solicitacao}/devolver', [SolicitacaoController::class, 'devolver']);

    // ─── ÉPICO 2: Triagem e Regulação ────────────────────────────────────────

    /**
     * POST /api/pareceres
     * Registra o parecer emitido pelo Médico Regulador (HU07 — Equipe 2).
     * Requisição: { id_solicitacao, texto_parecer }
     */
    Route::post('/pareceres', [ParecerController::class, 'store']);

    // ─── ÉPICO 3: Conclusão / NIR ─────────────────────────────────────────────

    // Médico solicitante responde ao pedido de informações (status Pendente → Aguarda triagem)
    Route::post('/solicitacoes/{solicitacao}/complementar', [SolicitacaoController::class, 'complementar']);

    // NIR finaliza solicitação triada: agendamento ou devolução do parecer
    Route::post('/solicitacoes/{solicitacao}/regulacao', [RegulacaoController::class, 'store']);
});
