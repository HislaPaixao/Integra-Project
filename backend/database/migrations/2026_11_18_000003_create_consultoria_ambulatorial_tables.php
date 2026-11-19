<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas do módulo de Consultoria Ambulatorial (seção 3.1 do Termo SEI).
 *
 * Ordem de criação respeita as chaves estrangeiras:
 *   pacientes → solicitacoes → prioridades / pareceres / agendamentos / historicos
 *
 * Observação: o AGHU é fonte externa. A tabela `pacientes` guarda uma cópia
 * local dos dados trazidos de lá (inclusive `nome`, necessário para a tela de
 * Avaliação de Parecer) para que a solicitação possa referenciar o paciente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id('id_paciente');
            $table->string('prontuario', 30)->unique();
            $table->string('nome', 150)->nullable();
            $table->string('cpf', 11)->nullable();
            $table->string('cartao_sus', 20)->nullable();
            $table->string('leito', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('solicitacoes', function (Blueprint $table) {
            $table->id('id_solicitacao');
            $table->foreignId('id_paciente')->constrained('pacientes', 'id_paciente');
            $table->foreignId('id_solicitante')->constrained('users');
            $table->string('especialidade', 100);
            $table->text('descricao_clinica');
            $table->string('status', 30)->default('Aguarda triagem');
            $table->timestamp('data_solicitacao')->useCurrent();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('prioridades', function (Blueprint $table) {
            $table->id('id_prioridade');
            $table->foreignId('id_solicitacao')->unique()->constrained('solicitacoes', 'id_solicitacao');
            $table->foreignId('id_regulador')->nullable()->constrained('users');
            $table->string('prioridade_ia', 2)->nullable();
            $table->string('prioridade_final', 2)->nullable();
            $table->text('justificativa')->nullable();
            $table->timestamps();
        });

        Schema::create('pareceres', function (Blueprint $table) {
            $table->id('id_parecer');
            $table->foreignId('id_solicitacao')->constrained('solicitacoes', 'id_solicitacao');
            $table->foreignId('id_regulador')->constrained('users');
            $table->text('texto_parecer');
            $table->timestamp('data_parecer')->useCurrent();
            $table->timestamps();
        });

        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id('id_agendamento');
            $table->foreignId('id_solicitacao')->constrained('solicitacoes', 'id_solicitacao');
            $table->timestamp('data_agendamento');
            $table->string('local', 150);
            $table->string('status', 30)->default('Agendado');
            $table->timestamps();
        });

        Schema::create('historicos', function (Blueprint $table) {
            $table->id('id_historico');
            $table->foreignId('id_solicitacao')->constrained('solicitacoes', 'id_solicitacao');
            $table->foreignId('id_usuario')->constrained('users');
            $table->string('acao', 80);
            $table->text('descricao')->nullable();
            $table->timestamp('data_hora')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historicos');
        Schema::dropIfExists('agendamentos');
        Schema::dropIfExists('pareceres');
        Schema::dropIfExists('prioridades');
        Schema::dropIfExists('solicitacoes');
        Schema::dropIfExists('pacientes');
    }
};
