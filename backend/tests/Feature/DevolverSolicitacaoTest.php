<?php

namespace Tests\Feature;

use App\Models\Solicitacao;
use App\Models\User;
use Tests\Concerns\MigraBancoDeTeste;
use Tests\Concerns\CriaDadosDeTeste;
use Tests\TestCase;

/**
 * Item 5 — Devolução: status vira "Pendente" e o motivo é obrigatório e registrado.
 */
class DevolverSolicitacaoTest extends TestCase
{
    use MigraBancoDeTeste;
    use CriaDadosDeTeste;

    private const MOTIVO = 'Informar o tipo de insulina utilizada e a dose diária.';

    public function test_regulador_devolve_solicitacao_e_motivo_fica_no_historico(): void
    {
        $solicitante = User::factory()->create();
        $regulador   = User::factory()->regulador()->create();
        $solicitacao = $this->criarSolicitacao($solicitante);
        $this->autenticarComo($regulador);

        $this->postJson("/api/solicitacoes/{$solicitacao->id_solicitacao}/devolver", ['motivo' => self::MOTIVO])
            ->assertOk()
            ->assertJsonPath('status', 'Pendente')
            ->assertJsonPath('motivo', self::MOTIVO);

        $this->assertSame('Pendente', $solicitacao->fresh()->status);
        $this->assertDatabaseHas('historicos', [
            'id_solicitacao' => $solicitacao->id_solicitacao,
            'id_usuario'     => $regulador->id,
            'acao'           => 'DEVOLUCAO',
            'descricao'      => self::MOTIVO,
        ]);
    }

    public function test_profissional_do_nir_tambem_pode_devolver(): void
    {
        $solicitacao = $this->criarSolicitacao(User::factory()->create());
        $this->autenticarComo(User::factory()->nir()->create());

        $this->postJson("/api/solicitacoes/{$solicitacao->id_solicitacao}/devolver", ['motivo' => self::MOTIVO])
            ->assertOk();
    }

    public function test_devolucao_sem_motivo_retorna_422_e_nao_muda_o_status(): void
    {
        $solicitacao = $this->criarSolicitacao(User::factory()->create());
        $this->autenticarComo(User::factory()->regulador()->create());

        $this->postJson("/api/solicitacoes/{$solicitacao->id_solicitacao}/devolver", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motivo');

        $this->assertSame(Solicitacao::STATUS_AGUARDA_TRIAGEM, $solicitacao->fresh()->status);
        $this->assertDatabaseCount('historicos', 0);
    }

    public function test_devolucao_com_motivo_muito_curto_retorna_422(): void
    {
        $solicitacao = $this->criarSolicitacao(User::factory()->create());
        $this->autenticarComo(User::factory()->regulador()->create());

        $this->postJson("/api/solicitacoes/{$solicitacao->id_solicitacao}/devolver", ['motivo' => 'falta'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motivo');
    }

    public function test_solicitante_nao_pode_devolver(): void
    {
        $solicitante = User::factory()->create();
        $solicitacao = $this->criarSolicitacao($solicitante);
        $this->autenticarComo($solicitante);

        $this->postJson("/api/solicitacoes/{$solicitacao->id_solicitacao}/devolver", ['motivo' => self::MOTIVO])
            ->assertForbidden();

        $this->assertSame(Solicitacao::STATUS_AGUARDA_TRIAGEM, $solicitacao->fresh()->status);
    }

    public function test_solicitacao_finalizada_nao_pode_ser_devolvida(): void
    {
        $solicitacao = $this->criarSolicitacao(User::factory()->create(), null, Solicitacao::STATUS_FINALIZADO);
        $this->autenticarComo(User::factory()->regulador()->create());

        $this->postJson("/api/solicitacoes/{$solicitacao->id_solicitacao}/devolver", ['motivo' => self::MOTIVO])
            ->assertUnprocessable();

        $this->assertSame(Solicitacao::STATUS_FINALIZADO, $solicitacao->fresh()->status);
    }

    public function test_devolver_solicitacao_inexistente_retorna_404(): void
    {
        $this->autenticarComo(User::factory()->regulador()->create());

        $this->postJson('/api/solicitacoes/9999/devolver', ['motivo' => self::MOTIVO])
            ->assertNotFound();
    }
}
