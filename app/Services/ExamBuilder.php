<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Monta un examen con preguntas aprobadas de la disciplina elegida. */
class ExamBuilder
{
    public function start(User $user, string $discipline, int $total, bool $includeOpen): Exam
    {
        $tests = ExamQuestion::where('discipline', $discipline)->where('status', 'approved')->where('type', 'test')->inRandomOrder()->pluck('id');
        $opens = ExamQuestion::where('discipline', $discipline)->where('status', 'approved')->where('type', 'open')->inRandomOrder()->pluck('id');

        $nOpen = $includeOpen ? min($opens->count(), intdiv($total, 4)) : 0;
        $nTest = $total - $nOpen;

        if ($tests->count() < $nTest) {
            throw new DomainException("De momento solo hay {$tests->count()} preguntas de test aprobadas en esta disciplina; pide menos preguntas o espera a que se amplíe el banco.");
        }

        $ids = $tests->take($nTest)->merge($opens->take($nOpen))->shuffle()->values();

        return DB::transaction(function () use ($user, $discipline, $ids) {
            $exam = Exam::create(['user_id' => $user->id, 'discipline' => $discipline, 'status' => 'in_progress', 'started_at' => now()]);
            foreach ($ids as $i => $id) {
                $exam->answers()->create(['question_id' => $id, 'position' => $i + 1, 'max_score' => 1]);
            }

            return $exam;
        });
    }
}
