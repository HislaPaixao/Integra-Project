<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\MigraBancoDeTeste;
use Tests\TestCase;

/**
 * Item 3 — Login real e logout (substitui o usuário fixo e o "demo-token").
 */
class AuthTest extends TestCase
{
    use MigraBancoDeTeste;

    private function medico(): User
    {
        return User::factory()->create([
            'name'     => 'Dr. Carlos Eduardo Mendes',
            'email'    => 'medico@hospital.gov.br',
            'password' => 'senha123',
            'crm'      => 'PR-54321',
            'perfil'   => 'solicitante',
        ]);
    }

    public function test_login_com_credenciais_validas_devolve_token_real_e_dados_do_usuario(): void
    {
        $this->medico();

        $resposta = $this->postJson('/api/auth/login', [
            'email'    => 'medico@hospital.gov.br',
            'password' => 'senha123',
        ]);

        $resposta->assertOk()
            ->assertJsonStructure(['token', 'usuario' => ['id', 'nome', 'crm', 'perfil']])
            ->assertJsonPath('usuario.nome', 'Dr. Carlos Eduardo Mendes')
            ->assertJsonPath('usuario.perfil', 'solicitante');

        $this->assertNotSame('demo-token', $resposta->json('token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_com_senha_errada_retorna_401(): void
    {
        $this->medico();

        $this->postJson('/api/auth/login', [
            'email'    => 'medico@hospital.gov.br',
            'password' => 'senha-errada',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciais inválidas.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_com_email_inexistente_retorna_401(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'ninguem@hospital.gov.br',
            'password' => 'senha123',
        ])->assertUnauthorized();
    }

    public function test_login_sem_campos_retorna_422(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_rota_protegida_sem_token_retorna_401_em_json(): void
    {
        $this->getJson('/api/solicitacoes')->assertUnauthorized();

        // Mesmo sem o cabeçalho "Accept: application/json" a resposta não pode virar erro 500.
        $this->get('/api/solicitacoes')->assertUnauthorized();
    }

    public function test_o_antigo_demo_token_nao_funciona_mais(): void
    {
        $this->withToken('demo-token')
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_token_do_login_da_acesso_ao_me(): void
    {
        $this->medico();

        $token = $this->postJson('/api/auth/login', [
            'email'    => 'medico@hospital.gov.br',
            'password' => 'senha123',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email', 'medico@hospital.gov.br')
            ->assertJsonMissingPath('password');
    }

    public function test_logout_revoga_o_token(): void
    {
        $this->medico();

        $token = $this->postJson('/api/auth/login', [
            'email'    => 'medico@hospital.gov.br',
            'password' => 'senha123',
        ])->json('token');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // O Laravel guarda o usuário em memória durante o teste; limpamos para simular uma nova requisição.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }
}
