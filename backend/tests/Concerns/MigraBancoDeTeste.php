<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;

/**
 * Prepara o banco de teste (SQLite em memória) criando todas as tabelas antes de cada teste.
 *
 * Por que não usar o RefreshDatabase padrão do Laravel? Ele chama `$this->artisan()`,
 * que depende do pacote `mockery/mockery` — hoje ausente do composer.json do projeto.
 * Esta versão chama as migrations direto e não precisa de nenhum pacote extra.
 *
 * Como cada teste recebe um banco novo em memória, não há dados sobrando entre testes.
 * (O Laravel executa automaticamente o método `setUp<NomeDoTrait>` ao iniciar cada teste.)
 */
trait MigraBancoDeTeste
{
    protected function setUpMigraBancoDeTeste(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }
}
