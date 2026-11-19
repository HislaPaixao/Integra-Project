<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\CriaDadosDeTeste;
use Tests\Concerns\MigraBancoDeTeste;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Item 4 (parte 1) — a busca no AGHU grava o paciente no banco e devolve o
 * `idPaciente`, que é o valor usado depois no cadastro da solicitação.
 * O AGHU é simulado (Http::fake): nenhum teste chama o sistema real.
 */
class PacienteAghuTest extends TestCase
{
    use MigraBancoDeTeste;
    use CriaDadosDeTeste;

    private function respostaDoAghu(): array
    {
        return [
            'nr_prontuario'     => '123456',
            'nm_paciente'       => 'Ana Beatriz Ferreira',
            'nr_cartao_sus'     => '7000.1234.5678.9012',
            'nr_cpf'            => '123.456.789-00',
            'ds_leito'          => 'Leito 402-A',
            'nr_consulta'       => '987654',
            'ds_clinica_origem' => 'Clínica Médica Interna',
        ];
    }

    public function test_busca_no_aghu_devolve_dados_e_grava_o_paciente(): void
    {
        Http::fake(['*' => Http::response($this->respostaDoAghu(), 200)]);
        $this->autenticarComo(User::factory()->create());

        $this->getJson('/api/pacientes/123456')
            ->assertOk()
            ->assertJsonPath('nomePaciente', 'Ana Beatriz Ferreira')
            ->assertJsonPath('clinicaOrigem', 'Clínica Médica Interna')
            ->assertJsonStructure(['idPaciente']);

        $this->assertDatabaseHas('pacientes', [
            'prontuario' => '123456',
            'nome'       => 'Ana Beatriz Ferreira',
            'cpf'        => '12345678900', // sem pontuação
        ]);
    }

    public function test_buscar_duas_vezes_o_mesmo_prontuario_nao_duplica_o_paciente(): void
    {
        Http::fake(['*' => Http::response($this->respostaDoAghu(), 200)]);
        $this->autenticarComo(User::factory()->create());

        $primeiro = $this->getJson('/api/pacientes/123456')->json('idPaciente');
        $segundo  = $this->getJson('/api/pacientes/123456')->json('idPaciente');

        $this->assertSame($primeiro, $segundo);
        $this->assertDatabaseCount('pacientes', 1);
    }

    public function test_prontuario_inexistente_no_aghu_retorna_404_e_nao_grava(): void
    {
        Http::fake(['*' => Http::response([], 404)]);
        $this->autenticarComo(User::factory()->create());

        $this->getJson('/api/pacientes/000000')
            ->assertNotFound()
            ->assertJsonPath('message', 'Paciente não encontrado no AGHU.');

        $this->assertDatabaseCount('pacientes', 0);
    }

    public function test_busca_exige_login(): void
    {
        $this->getJson('/api/pacientes/123456')->assertUnauthorized();
    }
}
