<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->char('discipline', 1);
            $table->string('type', 10);                       // test | open
            $table->string('difficulty', 10)->default('media'); // basica | media | dificil
            $table->text('statement');
            $table->json('options')->nullable();              // solo test: lista de textos
            $table->unsignedTinyInteger('correct_index')->nullable();
            $table->text('explanation')->nullable();
            $table->json('key_points')->nullable();           // solo abiertas: puntos que debe contener la respuesta
            $table->text('model_answer')->nullable();
            $table->string('rule_ref', 40);                   // regla en la que se basa
            $table->text('rule_quote');                       // frase literal en inglés, verificada
            $table->string('status', 10)->default('draft');   // draft | approved | rejected
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['discipline', 'status']);
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('discipline', 1);
            $table->string('status', 12)->default('in_progress'); // in_progress | submitted | graded
            $table->decimal('score', 4, 2)->nullable();           // sobre 10
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('exam_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->unsignedTinyInteger('selected_index')->nullable();
            $table->text('answer_text')->nullable();
            $table->decimal('max_score', 4, 2)->default(1);
            $table->decimal('score', 4, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->string('graded_by', 10)->nullable();          // auto | ai | admin
            $table->timestamps();
            $table->unique(['exam_id', 'question_id']);
        });

        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 30);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_questions');
    }
};
