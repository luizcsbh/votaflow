<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votacoes', function (Blueprint $table) {
            $table->id();
            // Identificador público (URL / QR Code). O id sequencial nunca é exposto ao participante.
            $table->string('public_id', 12)->unique();
            $table->string('titulo', 200);
            $table->text('descricao')->nullable();
            $table->string('imagem', 2048)->nullable();
            $table->string('status', 20)->default('RASCUNHO');
            // identificada | parcialmente_anonima | anonima
            $table->string('privacidade', 30)->default('identificada');
            $table->dateTime('inicio_em')->nullable();
            $table->dateTime('fim_em')->nullable();
            $table->unsignedInteger('limite_participantes')->nullable();
            // Contador atômico, usado apenas quando há limite de participantes.
            $table->unsignedInteger('participantes_count')->default(0);
            $table->boolean('permite_alterar_resposta')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'inicio_em']);
            $table->index(['status', 'fim_em']);
        });

        Schema::create('perguntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('votacao_id')->constrained('votacoes')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('titulo', 300);
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('obrigatoria')->default(true);
            $table->timestamps();

            $table->index(['votacao_id', 'ordem']);
        });

        Schema::create('alternativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pergunta_id')->constrained('perguntas')->cascadeOnDelete();
            $table->string('texto', 300);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['pergunta_id', 'ordem']);
        });

        Schema::create('participacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('votacao_id')->constrained('votacoes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('protocolo', 16)->unique();
            $table->dateTime('iniciado_em')->nullable();
            $table->dateTime('finalizado_em')->nullable();
            $table->timestamps();

            // Garantia de 1 usuário = 1 participação por votação, no nível do banco.
            $table->unique(['votacao_id', 'usuario_id'], 'participacoes_votacao_usuario_unique');
            $table->index(['votacao_id', 'finalizado_em']);
        });

        Schema::create('respostas', function (Blueprint $table) {
            $table->id();
            // NULL quando a votação é anônima: não existe vínculo entre identidade e resposta.
            $table->foreignId('participacao_id')->nullable()->constrained('participacoes')->cascadeOnDelete();
            $table->foreignId('pergunta_id')->constrained('perguntas')->cascadeOnDelete();
            $table->foreignId('alternativa_id')->nullable()->constrained('alternativas')->cascadeOnDelete();
            $table->string('valor', 500)->nullable();
            $table->timestamps();

            // Índice que sustenta a agregação dos resultados (GROUP BY).
            $table->index(['pergunta_id', 'alternativa_id']);
            $table->index('participacao_id');
        });

        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('votacao_id')->nullable()->constrained('votacoes')->nullOnDelete();
            $table->string('acao', 50)->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->json('dados')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['votacao_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('respostas');
        Schema::dropIfExists('participacoes');
        Schema::dropIfExists('alternativas');
        Schema::dropIfExists('perguntas');
        Schema::dropIfExists('votacoes');
    }
};
