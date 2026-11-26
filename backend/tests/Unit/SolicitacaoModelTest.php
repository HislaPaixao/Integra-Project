<?php

namespace Tests\Unit;

use App\Models\Solicitacao;
use App\Models\Paciente;
use App\Models\Parecer;
use App\Models\Prioridade;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class SolicitacaoModelTest extends TestCase
{
    // Teste 1: verifica o nome da tabela no banco de dados.
    public function test_nome_da_tabela(): void
    {
        $solicitacao = new Solicitacao();

        $this->assertEquals(
            'solicitacoes',
            $solicitacao->getTable()
        );
    }

    // Teste 2: verifica a chave primaria do modelo.
    public function test_chave_primaria(): void
    {
        $solicitacao = new Solicitacao();

        $this->assertEquals(
            'id_solicitacao',
            $solicitacao->getKeyName()
        );
    }

    // Teste 3: verifica os campos permitidos para preenchimento.
    public function test_campos_permitidos(): void
    {
        $solicitacao = new Solicitacao();

        $this->assertEquals([
            'id_paciente',
            'id_solicitante',
            'especialidade',
            'descricao_clinica',
            'status',
            'data_solicitacao',
        ], $solicitacao->getFillable());
    }

    // Teste 4: verifica os status definidos pelo sistema.
    public function test_status_permitidos(): void
    {
        $this->assertEquals([
            'Aguarda triagem',
            'Triado',
            'Pendente',
            'Finalizado',
        ], Solicitacao::STATUS);
    }

    // Teste 5: verifica os niveis de prioridade existentes.
    public function test_prioridades_permitidas(): void
    {
        $this->assertEquals([
            'P1',
            'P2',
            'P3',
        ], Solicitacao::PRIORIDADES);
    }

    // Teste 6: verifica o relacionamento com o paciente.
    public function test_relacionamento_com_paciente(): void
    {
        $solicitacao = new Solicitacao();

        $relacionamento = $solicitacao->paciente();

        $this->assertInstanceOf(
            BelongsTo::class,
            $relacionamento
        );

        $this->assertInstanceOf(
            Paciente::class,
            $relacionamento->getRelated()
        );

        $this->assertEquals(
            'id_paciente',
            $relacionamento->getForeignKeyName()
        );

        $this->assertEquals(
            'id_paciente',
            $relacionamento->getOwnerKeyName()
        );
    }

    // Teste 7: verifica o relacionamento com os pareceres.
    public function test_relacionamento_com_pareceres(): void
    {
        $solicitacao = new Solicitacao();

        $relacionamento = $solicitacao->pareceres();

        $this->assertInstanceOf(
            HasMany::class,
            $relacionamento
        );

        $this->assertInstanceOf(
            Parecer::class,
            $relacionamento->getRelated()
        );

        $this->assertEquals(
            'id_solicitacao',
            $relacionamento->getForeignKeyName()
        );

        $this->assertEquals(
            'id_solicitacao',
            $relacionamento->getLocalKeyName()
        );
    }

    // Teste 8: verifica o relacionamento com a prioridade.
    public function test_relacionamento_com_prioridade(): void
    {
        $solicitacao = new Solicitacao();

        $relacionamento = $solicitacao->prioridade();

        $this->assertInstanceOf(
            HasOne::class,
            $relacionamento
        );

        $this->assertInstanceOf(
            Prioridade::class,
            $relacionamento->getRelated()
        );

        $this->assertEquals(
            'id_solicitacao',
            $relacionamento->getForeignKeyName()
        );

        $this->assertEquals(
            'id_solicitacao',
            $relacionamento->getLocalKeyName()
        );
    }
}
