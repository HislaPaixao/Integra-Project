<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\MigraBancoDeTeste;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Teste de integração do caminho completo, usando login real (sem atalhos):
 *
 *   médico faz login → busca paciente no AGHU → cadastra solicitação
 *   → regulador faz login → devolve com motivo (Pendente)
 *   → médico complementa a descrição.
 */
class FluxoConsultoriaTest extends TestCase
{
    use MigraBancoDeTeste;

    private function login(string $email): string
    {
        // Simula uma nova requisição: esquece o usuário guardado em memória pela anterior.
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'senha123'])
            ->assertOk()
            ->json('token');
    }

    public function test_fluxo_completo_da_solicitacao_ate_a_devolucao(): void
    {
        Http::fake(['*' => Http::response([
            'nr_prontuario' => '123456',
            'nm_paciente'   => 'Ana Beatriz Ferreira',
            'nr_cartao_sus' => '7000.1234.5678.9012',
            'nr_cpf'        => '123.456.789-00',
            'ds_leito'      => 'Leito 402-A',
        ], 200)]);

        User::factory()->create(['email' => 'medico@hospital.gov.br', 'password' => 'senha123']);
        User::factory()->regulador()->create(['email' => 'regulador@hospital.gov.br', 'password' => 'senha123']);

        // 1) Médico solicitante
        $tokenMedico = $this->login('medico@hospital.gov.br');

        $idPaciente = $this->withToken($tokenMedico)
            ->getJson('/api/pacientes/123456')
            ->assertOk()
            ->json('idPaciente');

        $idSolicitacao = $this->withToken($tokenMedico)
            ->postJson('/api/solicitacoes', [
                'id_paciente'       => $idPaciente,
                'especialidade'     => 'Endocrinologia adulto',
                'descricao_clinica' => 'Paciente com diabetes tipo 2 descompensada.',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'Aguarda triagem')
            ->json('id');

        // 2) Médico regulador devolve pedindo complemento
        $tokenRegulador = $this->login('regulador@hospital.gov.br');

        $this->withToken($tokenRegulador)
            ->postJson("/api/solicitacoes/{$idSolicitacao}/devolver", [
                'motivo' => 'Informar tipo de insulina utilizada e dose diária.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'Pendente');

        // 3) Médico solicitante complementa
        $tokenMedico = $this->login('medico@hospital.gov.br');

        $this->withToken($tokenMedico)
            ->putJson("/api/solicitacoes/{$idSolicitacao}", [
                'descricao_clinica' => 'Paciente com diabetes tipo 2 descompensada, insulina NPH 20 UI à noite.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('historicos', [
            'id_solicitacao' => $idSolicitacao,
            'acao'           => 'DEVOLUCAO',
        ]);
    }
}
