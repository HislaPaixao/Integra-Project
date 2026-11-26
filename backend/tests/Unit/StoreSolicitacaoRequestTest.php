<?php

namespace Tests\Unit;

use App\Http\Requests\StoreSolicitacaoRequest;
use App\Models\Solicitacao;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreSolicitacaoRequestTest extends TestCase
{
    // Valida um campo isoladamente, sem acessar o banco de dados.
    private function validarCampo(string $campo, $valor): bool
    {
        $request = new StoreSolicitacaoRequest();
        $regras = $request->rules();

        $validator = Validator::make(
            [$campo => $valor],
            [$campo => $regras[$campo]]
        );

        return $validator->passes();
    }

    // Teste 1: rejeita descrições menores que 20 caracteres.
    public function test_rejeita_descricao_curta(): void
    {
        $this->assertFalse(
            $this->validarCampo('descricao_clinica', str_repeat('a', 19))
        );
    }

    // Teste 2: aceita descrições com exatamente 20 caracteres.
    public function test_aceita_descricao_com_20_caracteres(): void
    {
        $this->assertTrue(
            $this->validarCampo('descricao_clinica', str_repeat('a', 20))
        );
    }

    // Teste 3: aceita descrições maiores que 20 caracteres.
    public function test_aceita_descricao_longa(): void
    {
        $this->assertTrue(
            $this->validarCampo(
                'descricao_clinica',
                'Paciente necessita de acompanhamento especializado.'
            )
        );
    }

    // Teste 4: rejeita descrições vazias.
    public function test_rejeita_descricao_vazia(): void
    {
        $this->assertFalse(
            $this->validarCampo('descricao_clinica', '')
        );
    }

    // Teste 5: aceita uma especialidade permitida pelo sistema.
    public function test_aceita_especialidade_valida(): void
    {
        $this->assertTrue(
            $this->validarCampo(
                'especialidade',
                Solicitacao::ESPECIALIDADES[0]
            )
        );
    }

    // Teste 6: rejeita uma especialidade inexistente.
    public function test_rejeita_especialidade_invalida(): void
    {
        $this->assertFalse(
            $this->validarCampo('especialidade', 'Especialidade inexistente')
        );
    }
}
