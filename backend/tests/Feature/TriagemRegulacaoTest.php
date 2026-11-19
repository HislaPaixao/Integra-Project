<?php

namespace Tests\Feature;

use App\Models\Solicitacao;
use App\Models\User;
use Tests\Concerns\CriaDadosDeTeste;
use Tests\Concerns\MigraBancoDeTeste;
use Tests\TestCase;

class TriagemRegulacaoTest extends TestCase
{
    use MigraBancoDeTeste, CriaDadosDeTeste;

    private function medico(): User { return User::factory()->create(); }
    private function regulador(): User { return User::factory()->regulador()->create(); }
    private function nir(): User { return User::factory()->nir()->create(); }

    public function test_regulador_tria_com_prioridade(): void
    {
        $s = $this->criarSolicitacao($this->medico());
        $this->autenticarComo($this->regulador());

        $this->postJson('/api/pareceres', [
            'id_solicitacao' => $s->id_solicitacao, 'texto_parecer' => 'Indicado atendimento.',
            'status' => 'Triado', 'prioridade' => 'P2',
        ])->assertCreated()->assertJsonPath('status', 'Triado');

        $this->assertSame('Triado', $s->fresh()->status);
        $this->assertSame('P2', $s->prioridade()->first()->prioridade_final);
        $this->assertSame('TRIAGEM', $s->historico()->latest('id_historico')->first()->acao);
    }

    public function test_triado_exige_prioridade(): void
    {
        $s = $this->criarSolicitacao($this->medico());
        $this->autenticarComo($this->regulador());
        $this->postJson('/api/pareceres', [
            'id_solicitacao' => $s->id_solicitacao, 'texto_parecer' => 'Indicado atendimento.', 'status' => 'Triado',
        ])->assertStatus(422)->assertJsonValidationErrors('prioridade');
    }

    public function test_solicitante_nao_emite_parecer(): void
    {
        $m = $this->medico();
        $s = $this->criarSolicitacao($m);
        $this->autenticarComo($m);
        $this->postJson('/api/pareceres', [
            'id_solicitacao' => $s->id_solicitacao, 'texto_parecer' => 'Indicado atendimento.',
            'status' => 'Triado', 'prioridade' => 'P1',
        ])->assertForbidden();
    }

    public function test_pendencia_e_complemento_voltam_para_triagem(): void
    {
        $m = $this->medico();
        $s = $this->criarSolicitacao($m);
        $this->autenticarComo($this->regulador());
        $this->postJson('/api/pareceres', [
            'id_solicitacao' => $s->id_solicitacao, 'texto_parecer' => 'Informar insulina usada.', 'status' => 'Pendente',
        ])->assertCreated();
        $this->assertSame('Pendente', $s->fresh()->status);

        $this->autenticarComo($m);
        $this->postJson("/api/solicitacoes/{$s->id_solicitacao}/complementar", ['texto' => 'Usa NPH 20UI à noite.'])->assertOk();
        $this->assertSame('Aguarda triagem', $s->fresh()->status);
        $this->assertSame('COMPLEMENTO', $s->historico()->latest('id_historico')->first()->acao);
    }

    public function test_complementar_so_quando_pendente(): void
    {
        $m = $this->medico();
        $s = $this->criarSolicitacao($m);
        $this->autenticarComo($m);
        $this->postJson("/api/solicitacoes/{$s->id_solicitacao}/complementar", ['texto' => 'Texto suficiente aqui.'])->assertForbidden();
    }

    public function test_nir_agenda_e_finaliza(): void
    {
        $s = $this->criarSolicitacao($this->medico(), null, Solicitacao::STATUS_TRIADO);
        $this->autenticarComo($this->nir());
        $this->postJson("/api/solicitacoes/{$s->id_solicitacao}/regulacao", [
            'conduta' => 'agendamento', 'justificativa' => 'Vaga disponível.',
            'data_agendamento' => now()->addDays(10)->format('Y-m-d H:i:s'), 'local' => 'Ambulatório HUB',
        ])->assertOk()->assertJsonPath('status', 'Finalizado');
        $this->assertSame(1, $s->agendamentos()->count());
    }

    public function test_nir_agendamento_exige_data_e_local(): void
    {
        $s = $this->criarSolicitacao($this->medico(), null, Solicitacao::STATUS_TRIADO);
        $this->autenticarComo($this->nir());
        $this->postJson("/api/solicitacoes/{$s->id_solicitacao}/regulacao", [
            'conduta' => 'agendamento', 'justificativa' => 'Vaga disponível.',
        ])->assertStatus(422)->assertJsonValidationErrors(['data_agendamento', 'local']);
    }

    public function test_regulacao_so_para_triado_e_so_nir(): void
    {
        $s = $this->criarSolicitacao($this->medico());
        $this->autenticarComo($this->nir());
        $this->postJson("/api/solicitacoes/{$s->id_solicitacao}/regulacao", [
            'conduta' => 'devolucao_parecer', 'justificativa' => 'Atenção básica.',
        ])->assertStatus(422);

        $this->autenticarComo($this->regulador());
        $this->postJson("/api/solicitacoes/{$s->id_solicitacao}/regulacao", [
            'conduta' => 'devolucao_parecer', 'justificativa' => 'Atenção básica.',
        ])->assertForbidden();
    }

    public function test_listagem_filtra_por_perfil_e_status(): void
    {
        $a = $this->medico(); $b = $this->medico();
        $this->criarSolicitacao($a);
        $this->criarSolicitacao($b, $this->criarPaciente(['prontuario' => '654321']), Solicitacao::STATUS_TRIADO);

        $this->autenticarComo($a);
        $this->getJson('/api/solicitacoes')->assertOk()->assertJsonCount(1);

        $this->autenticarComo($this->regulador());
        $this->getJson('/api/solicitacoes')->assertJsonCount(2);
        $this->getJson('/api/solicitacoes?status=Triado')->assertJsonCount(1);
    }

    public function test_solicitante_nao_ve_solicitacao_de_outro(): void
    {
        $s = $this->criarSolicitacao($this->medico());
        $this->autenticarComo($this->medico());
        $this->getJson("/api/solicitacoes/{$s->id_solicitacao}")->assertForbidden();
    }
}
