<?php

namespace Tests\Concerns;

use App\Models\Paciente;
use App\Models\Solicitacao;
use App\Models\User;

/**
 * Atalhos para os testes: montar paciente/solicitação e entrar como um usuário.
 */
trait CriaDadosDeTeste
{
    /**
     * Passa a enviar, nas próximas requisições do teste, um token REAL do usuário
     * (o mesmo tipo de token que o login devolve). Não usamos Sanctum::actingAs()
     * porque ele depende do pacote mockery, ausente do projeto.
     */
    protected function autenticarComo(User $usuario): void
    {
        // Esquece o usuário guardado em memória pela requisição anterior do mesmo teste.
        $this->app['auth']->forgetGuards();

        $this->withToken($usuario->createToken('teste')->plainTextToken);
    }

    protected function criarPaciente(array $atributos = []): Paciente
    {
        return Paciente::create($atributos + [
            'prontuario' => '123456',
            'nome'       => 'Ana Beatriz Ferreira',
            'cpf'        => '12345678900',
            'cartao_sus' => '700012345678901',
            'leito'      => '402-A',
        ]);
    }

    protected function criarSolicitacao(
        User $solicitante,
        ?Paciente $paciente = null,
        string $status = Solicitacao::STATUS_AGUARDA_TRIAGEM
    ): Solicitacao {
        $paciente ??= $this->criarPaciente();

        return Solicitacao::create([
            'id_paciente'       => $paciente->id_paciente,
            'id_solicitante'    => $solicitante->id,
            'especialidade'     => 'Endocrinologia adulto',
            'descricao_clinica' => 'Paciente com diabetes tipo 2 descompensada, em uso de metformina.',
            'status'            => $status,
            'data_solicitacao'  => now(),
        ]);
    }

    /** Dados válidos para POST /api/solicitacoes. */
    protected function dadosDeSolicitacao(int $idPaciente, array $sobrescrever = []): array
    {
        return $sobrescrever + [
            'id_paciente'       => $idPaciente,
            'especialidade'     => 'Endocrinologia adulto',
            'descricao_clinica' => 'Paciente com diabetes tipo 2 descompensada, em uso de metformina.',
        ];
    }
}
