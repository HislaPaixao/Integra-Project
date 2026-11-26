<?php

namespace Tests\Unit;

use App\Services\AghuService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AghuServiceTest extends TestCase
{
    // Configura o ambiente antes de cada teste.
    protected function setUp(): void
    {
        parent::setUp();

        // Configura uma API fictícia para os testes.
        config([
            'services.aghu.mock' => false,
            'services.aghu.base_url' => 'https://aghu.test',
            'services.aghu.token' => 'token-teste',
            'services.aghu.timeout' => 5,
        ]);

        // Impede requisições HTTP reais.
        Http::preventStrayRequests();
    }

    // Teste 1: verifica a busca de um paciente existente.
    public function test_busca_paciente_existente(): void
    {
        Http::fake([
            'https://aghu.test/pacientes/12345' => Http::response([
                'nr_prontuario' => '12345',
                'nm_paciente' => 'Paciente Teste',
                'ds_leito' => '101',
                'ds_clinica_origem' => 'Cardiologia',
            ], 200),
        ]);

        $service = new AghuService();
        $resultado = $service->buscarPaciente('12345');

        $this->assertIsArray($resultado);
        $this->assertEquals('12345', $resultado['prontuario']);
        $this->assertEquals('Paciente Teste', $resultado['nomePaciente']);
        $this->assertEquals('101', $resultado['leito']);
        $this->assertEquals('Cardiologia', $resultado['clinicaOrigem']);
    }

    // Teste 2: verifica o comportamento quando o paciente não existe.
    public function test_paciente_inexistente(): void
    {
        Http::fake([
            'https://aghu.test/pacientes/99999' => Http::response([], 404),
        ]);

        $service = new AghuService();

        $this->assertNull(
            $service->buscarPaciente('99999')
        );
    }

    // Teste 3: verifica o tratamento de erros no servidor externo.
    public function test_erro_no_servidor(): void
    {
        Http::fake([
            'https://aghu.test/pacientes/12345' => Http::response([], 500),
        ]);

        $service = new AghuService();

        $this->assertNull(
            $service->buscarPaciente('12345')
        );
    }

    // Teste 4: simula uma falha de conexão com o AGHU.
    public function test_falha_de_conexao(): void
    {
        Http::fake([
            'https://aghu.test/*' => Http::failedConnection(),
        ]);

        $service = new AghuService();

        $this->assertNull(
            $service->buscarPaciente('12345')
        );
    }

    // Teste 5: verifica a remoção de espaços do prontuário.
    public function test_remove_espacos_do_prontuario(): void
    {
        Http::fake([
            'https://aghu.test/pacientes/12345' => Http::response([
                'nr_prontuario' => '12345',
                'nm_paciente' => 'Paciente Teste',
            ], 200),
        ]);

        $service = new AghuService();
        $resultado = $service->buscarPaciente('  12345  ');

        $this->assertNotNull($resultado);
        $this->assertEquals('12345', $resultado['prontuario']);

        Http::assertSent(function ($request) {
            return $request->url() ===
                'https://aghu.test/pacientes/12345';
        });
    }
}

