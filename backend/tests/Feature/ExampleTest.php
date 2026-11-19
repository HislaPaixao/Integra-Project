<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A página inicial não tem conteúdo próprio: redireciona para a documentação Swagger.
     * (O teste de exemplo do Laravel esperava status 200 e falhava por causa desse redirecionamento.)
     */
    public function test_a_pagina_inicial_redireciona_para_a_documentacao(): void
    {
        $this->get('/')->assertRedirect('/api/documentation');
    }
}
