<?php

namespace Tests\Feature;

use App\Models\Solicitacao;
use App\Models\User;
use Tests\Concerns\MigraBancoDeTeste;
use Tests\Concerns\CriaDadosDeTeste;
use Tests\TestCase;

/**
 * Itens 2 e 4 — Atualizar solicitação (UpdateSolicitacaoRequest) e
 * conferir se o paciente existe antes de cadastrar.
 */
class SolicitacaoTest extends TestCase
{
    use MigraBancoDeTeste;
    use CriaDadosDeTeste;

    // ── Cadastro (POST /api/solicitacoes) ────────────────────────────────

    public function test_solicitante_cadastra_solicitacao_com_paciente_existente(): void
    {
        $medico   = User::factory()->create();
        $paciente = $this->criarPaciente();
        $this->autenticarComo($medico);

        $this->postJson('/api/solicitacoes', $this->dadosDeSolicitacao($paciente->id_paciente))
            ->assertCreated()
            ->assertJsonPath('status', Solicitacao::STATUS_AGUARDA_TRIAGEM);

        $this->assertDatabaseHas('solicitacoes', [
            'id_paciente'    => $paciente->id_paciente,
            'id_solicitante' => $medico->id,
            'status'         => 'Aguarda triagem',
        ]);
    }

    public function test_cadastro_com_paciente_inexistente_retorna_422_e_nao_grava(): void
    {
        $this->autenticarComo(User::factory()->create());

        $this->postJson('/api/solicitacoes', $this->dadosDeSolicitacao(9999))
            ->assertUnprocessable()
            ->assertJsonPath('errors.id_paciente.0', 'Paciente não encontrado no sistema.');

        $this->assertDatabaseCount('solicitacoes', 0);
    }

    public function test_cadastro_sem_campos_obrigatorios_retorna_422(): void
    {
        $this->autenticarComo(User::factory()->create());

        $this->postJson('/api/solicitacoes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_paciente', 'especialidade', 'descricao_clinica']);
    }

    public function test_regulador_nao_pode_cadastrar_solicitacao(): void
    {
        $paciente = $this->criarPaciente();
        $this->autenticarComo(User::factory()->regulador()->create());

        $this->postJson('/api/solicitacoes', $this->dadosDeSolicitacao($paciente->id_paciente))
            ->assertForbidden();

        $this->assertDatabaseCount('solicitacoes', 0);
    }

    // ── Atualização (PUT /api/solicitacoes/{id}) ─────────────────────────

    public function test_solicitante_atualiza_a_propria_solicitacao(): void
    {
        $medico      = User::factory()->create();
        $solicitacao = $this->criarSolicitacao($medico);
        $this->autenticarComo($medico);

        $this->putJson("/api/solicitacoes/{$solicitacao->id_solicitacao}", [
            'descricao_clinica' => 'Descrição atualizada: HbA1c 9,2% e uso de insulina NPH.',
        ])->assertOk();

        $this->assertDatabaseHas('solicitacoes', [
            'id_solicitacao'    => $solicitacao->id_solicitacao,
            'descricao_clinica' => 'Descrição atualizada: HbA1c 9,2% e uso de insulina NPH.',
        ]);
    }

    public function test_atualizacao_nao_altera_status_nem_paciente_nem_solicitante(): void
    {
        $medico      = User::factory()->create();
        $outroMedico = User::factory()->create();
        $solicitacao = $this->criarSolicitacao($medico);
        $outroPaciente = $this->criarPaciente(['prontuario' => '654321']);
        $this->autenticarComo($medico);

        $this->putJson("/api/solicitacoes/{$solicitacao->id_solicitacao}", [
            'especialidade'  => 'Urologia',
            'status'         => 'Finalizado',
            'id_paciente'    => $outroPaciente->id_paciente,
            'id_solicitante' => $outroMedico->id,
        ])->assertOk();

        $solicitacao->refresh();
        $this->assertSame('Urologia', $solicitacao->especialidade);
        $this->assertSame(Solicitacao::STATUS_AGUARDA_TRIAGEM, $solicitacao->status);
        $this->assertSame($medico->id, (int) $solicitacao->id_solicitante);
        $this->assertNotSame($outroPaciente->id_paciente, $solicitacao->id_paciente);
    }

    public function test_atualizacao_com_descricao_curta_retorna_422(): void
    {
        $medico      = User::factory()->create();
        $solicitacao = $this->criarSolicitacao($medico);
        $this->autenticarComo($medico);

        $this->putJson("/api/solicitacoes/{$solicitacao->id_solicitacao}", [
            'descricao_clinica' => 'curta',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('descricao_clinica');
    }

    public function test_outro_solicitante_nao_pode_atualizar(): void
    {
        $dono        = User::factory()->create();
        $intruso     = User::factory()->create();
        $solicitacao = $this->criarSolicitacao($dono);
        $this->autenticarComo($intruso);

        $this->putJson("/api/solicitacoes/{$solicitacao->id_solicitacao}", [
            'especialidade' => 'Urologia',
        ])->assertForbidden();

        $this->assertSame('Endocrinologia adulto', $solicitacao->fresh()->especialidade);
    }

    public function test_solicitacao_finalizada_nao_pode_ser_atualizada(): void
    {
        $medico      = User::factory()->create();
        $solicitacao = $this->criarSolicitacao($medico, null, Solicitacao::STATUS_FINALIZADO);
        $this->autenticarComo($medico);

        $this->putJson("/api/solicitacoes/{$solicitacao->id_solicitacao}", [
            'especialidade' => 'Urologia',
        ])->assertForbidden();
    }

    public function test_solicitacao_pendente_pode_ser_atualizada_para_complementar(): void
    {
        $medico      = User::factory()->create();
        $solicitacao = $this->criarSolicitacao($medico, null, Solicitacao::STATUS_PENDENTE);
        $this->autenticarComo($medico);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id_solicitacao}", [
            'descricao_clinica' => 'Complemento: paciente usa insulina NPH 20 UI à noite.',
        ])->assertOk();
    }

    // ── Consulta ─────────────────────────────────────────────────────────

    public function test_listar_e_consultar_solicitacao(): void
    {
        $medico      = User::factory()->create();
        $solicitacao = $this->criarSolicitacao($medico);
        $this->autenticarComo($medico);

        $this->getJson('/api/solicitacoes')
            ->assertOk()
            ->assertJsonCount(1);

        $this->getJson("/api/solicitacoes/{$solicitacao->id_solicitacao}")
            ->assertOk()
            ->assertJsonPath('paciente.prontuario', '123456')
            ->assertJsonStructure(['pareceres', 'historico']);
    }

    public function test_consultar_solicitacao_inexistente_retorna_404(): void
    {
        $this->autenticarComo(User::factory()->create());

        $this->getJson('/api/solicitacoes/9999')->assertNotFound();
    }
}
