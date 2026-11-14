<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('arbitro')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            $table->unsignedInteger('monthly_quota')->default(60)->after('is_active');
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('role', 20)->default('arbitro');
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rulebooks', function (Blueprint $table) {
            $table->id();
            $table->string('edition')->unique();
            $table->boolean('active')->default(true);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rulebook_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->unsignedTinyInteger('depth');
            $table->unsignedTinyInteger('chapter');
            $table->string('chapter_title')->nullable();
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('page')->nullable();
            $table->unique(['rulebook_id', 'number']);
        });

        Schema::create('rule_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained()->cascadeOnDelete();
            // Letras de las disciplinas a las que aplica, p. ej. "HRSP"
            $table->string('disciplines', 6);
            $table->text('text');
        });

        Schema::create('glossary_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rulebook_id')->constrained()->cascadeOnDelete();
            $table->string('term');
            $table->string('disciplines', 6);
            $table->text('definition');
        });

        Schema::create('appendices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rulebook_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('title');
            $table->string('disciplines', 6);
            $table->unsignedSmallInteger('page')->nullable();
            $table->longText('text');
        });

        Schema::create('ask_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('discipline', 1);
            $table->text('question');
            $table->longText('answer')->nullable();
            $table->json('citations')->nullable();
            $table->string('status', 20)->default('ok'); // ok | no_info | error
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        // Índice de búsqueda de texto completo (una fila por variante, glosario y trozo de apéndice)
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                "CREATE VIRTUAL TABLE rule_search USING fts5(
                    ref UNINDEXED, kind UNINDEXED, disciplines UNINDEXED,
                    title, body, tokenize = 'porter unicode61'
                )"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TABLE IF EXISTS rule_search');
        }
        Schema::dropIfExists('ask_logs');
        Schema::dropIfExists('appendices');
        Schema::dropIfExists('glossary_terms');
        Schema::dropIfExists('rule_variants');
        Schema::dropIfExists('rules');
        Schema::dropIfExists('rulebooks');
        Schema::dropIfExists('invitations');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'monthly_quota']);
        });
    }
};
