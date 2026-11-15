<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAnswer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Resultados de todos los exámenes, corrección manual y estadísticas por regla. */
class ExamResultController extends Controller
{
    public function index(): View
    {
        $exams = Exam::with('user')->where('status', '!=', 'in_progress')->latest('submitted_at')->limit(100)->get();

        // Reglas en las que más se falla (sobre respuestas ya corregidas)
        $stats = DB::table('exam_answers as a')
            ->join('exam_questions as q', 'q.id', '=', 'a.question_id')
            ->whereNotNull('a.score')
            ->groupBy('q.rule_ref', 'q.discipline')
            ->selectRaw('q.rule_ref, q.discipline, count(*) as total, sum(case when a.score < a.max_score * 0.5 then 1 else 0 end) as fails')
            ->havingRaw('count(*) >= 3')
            ->orderByRaw('sum(case when a.score < a.max_score * 0.5 then 1.0 else 0 end) / count(*) desc')
            ->limit(15)->get();

        return view('admin.exam-results', compact('exams', 'stats'));
    }

    public function show(Exam $exam): View
    {
        $exam->load('answers.question', 'user');

        return view('exams.result', ['exam' => $exam, 'admin' => true]);
    }

    public function override(Request $request, Exam $exam, ExamAnswer $answer): RedirectResponse
    {
        abort_unless($answer->exam_id === $exam->id, 404);
        $data = $request->validate(['score' => 'required|numeric|min:0|max:'.$answer->max_score, 'feedback' => 'nullable|string|max:500']);

        $answer->update(['score' => (float) $data['score'], 'feedback' => $data['feedback'] ?: $answer->feedback, 'graded_by' => 'admin']);
        if ($exam->answers()->whereNull('graded_by')->doesntExist()) {
            $exam->update(['status' => 'graded', 'graded_at' => $exam->graded_at ?? now()]);
        }
        $exam->recalculate();

        return back()->with('status', 'Nota actualizada.');
    }
}
