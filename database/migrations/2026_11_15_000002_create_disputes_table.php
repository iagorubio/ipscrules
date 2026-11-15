<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ask_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('comment');                              // qué esperaba el árbitro / por qué no está de acuerdo
            $table->string('suggested_rule', 40)->nullable();     // regla que propone el árbitro
            $table->string('status', 10)->default('open');        // open | confirmed | rejected
            $table->string('correct_rule', 80)->nullable();       // regla(s) correcta(s), fijada por el admin al confirmar
            $table->text('admin_note')->nullable();
            $table->timestamp('mailed_at')->nullable();
            $table->timestamps();
            $table->unique(['ask_log_id', 'user_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
