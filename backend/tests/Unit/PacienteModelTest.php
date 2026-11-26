<?php

namespace Tests\Unit;

use App\Models\Paciente;
use App\Models\Solicitacao;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class PacienteModelTest extends TestCase
{
    // Teste 1: verifica o nome da tabela no banco de dados.
    public function test_nome_da_tabela(): void
    {
        $paciente = new Paciente();

        $this->assertEquals('pacientes', $paciente->getTable());
    }

    // Teste 2: verifica a chave primária do modelo.
    public function test_chave_primaria(): void
    {
        $paciente = new Paciente();

        $this->assertEquals('id_paciente', $paciente->getKeyName());
    }

    // Teste 3: verifica os campos permitidos para preenchimento.
    public function test_campos_permitidos(): void
    {
        $paciente = new Paciente();

        $this->assertEquals([
            'prontuario',
            'nome',
            'cpf',
            'cartao_sus',
            'leito',
        ], $paciente->getFillable());
    }

    // Teste 4: verifica a atribuição dos dados do paciente.
    public function test_atribuicao_de_dados(): void
    {
        $paciente = new Paciente([
            'prontuario' => '12345',
            'nome' => 'Paciente Teste',
            'cpf' => '00000000000',
            'cartao_sus' => '000000000000000',
            'leito' => '101',
        ]);

        $this->assertEquals('12345', $paciente->prontuario);
        $this->assertEquals('Paciente Teste', $paciente->nome);
        $this->assertEquals('00000000000', $paciente->cpf);
        $this->assertEquals('000000000000000', $paciente->cartao_sus);
        $this->assertEquals('101', $paciente->leito);
    }

    // Teste 5: verifica o relacionamento entre paciente e solicitacoes.
    public function test_relacionamento_com_solicitacoes(): void
    {
        $paciente = new Paciente();

        $relacionamento = $paciente->solicitacoes();

        $this->assertInstanceOf(HasMany::class, $relacionamento);

        $this->assertInstanceOf(
            Solicitacao::class,
            $relacionamento->getRelated()
        );
    }

    // Teste 6: verifica as chaves utilizadas no relacionamento.
    public function test_chaves_do_relacionamento(): void
    {
        $paciente = new Paciente();

        $relacionamento = $paciente->solicitacoes();

        $this->assertEquals(
            'id_paciente',
            $relacionamento->getForeignKeyName()
        );

        $this->assertEquals(
            'id_paciente',
            $relacionamento->getLocalKeyName()
        );
    }
}
