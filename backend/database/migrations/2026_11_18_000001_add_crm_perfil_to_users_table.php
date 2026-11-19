<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acrescenta à tabela `users` os campos que o módulo precisa:
 *   - crm:    CRM do médico (preenche o formulário de solicitação — HU01)
 *   - perfil: solicitante | regulador | nir (3.1.1 Entidade USUÁRIO)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('crm', 20)->nullable()->after('email');
            $table->string('perfil', 30)->default('solicitante')->after('crm');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['crm', 'perfil']);
        });
    }
};
