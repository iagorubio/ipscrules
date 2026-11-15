<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Services\ExamBuilder;
use App\Services\ExamGrader;
use App\Support\Disciplines;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Exámenes del árbitro: empezar, responder, entregar y ver el resultado. */
class ExamController extends Controller
{
    public function index(Request $request): View
    {
        $available = ExamQuestion::where('status', 'approved')->selectRaw('discipline, type, count(*) as n')->groupBy('discipline', 'type')->get();

        return view('exams.index', [
            'exams' => $request->user()->exams()->latest()->limit(20)->get(),
            'available' => $available,
            'usedThisMonth' => $request->user()->exams()->where('created_at', '>=', now()->startOfMonth())->count(),
            'monthlyLimit' => config('ipscrules.exams_per_month'),
            'discipline' => $request->session()->get('discipline', 'H'),
        ]);
    }

    public function start(Request $request, ExamBuilder $builder): RedirectResponse
    {
        $data = $request->validate([
            'discipline' => ['required', Rule::in(Disciplines::codes())],
            'total' => 'required|integer|min:5|max:30',
        ]);
        $user = $request->user();

        if ($user->exams()->where('created_at', '>=', now()->startOfMonth())->count() >= config('ipscrules.exams_per_month')) {
            return back()->withInput()->withErrors(['total' => 'Has alcanzado el máximo de exámenes de este mes.']);
        }

        // Si ya hay uno empezado en esa disciplina, se continúa en lugar de crear otro
        $open = $user->exams()->where('status', 'in_progress')->where('discipline', $data['discipline'])->latest()->first();
        if ($open) {
            return redirect()->route('exams.show', $open);
        }

        try {
            $exam = $builder->start($user, $data['discipline'], (int) $data['total'], $request->boolean('include_open'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['total' => $e->getMessage()]);
        }

        return redirect()->route('exams.show', $exam);
    }

    public function show(Request $request, Exam $exam): View
    {
        $this->authorizeExam($request, $exam);
        $exam->load('answers.question');

        return view($exam->inProgress() ? 'exams.take' : 'exams.result', ['exam' => $exam, 'admin' => false]);
    }

    public function submit(Request $request, Exam $exam, ExamGrader $grader): RedirectResponse
    {
        $this->authorizeExam($request, $exam, ownerOnly: true);
        abort_unless($exam->inProgress(), 409, 'Este examen ya se entregó.');

        $input = $request->input('a', []);
        $exam->load('answers.question');
        foreach ($exam->answers as $a) {
            $value = $input[$a->id] ?? null;
            if ($a->question->isTest()) {
                $a->selected_index = (is_numeric($value) && (int) $value >= 0 && (int) $value < count($a->question->options)) ? (int) $value : null;
            } else {
                $a->answer_text = mb_substr(trim((string) $value), 0, 3000) ?: null;
            }
            $a->save();
        }

        if ($request->input('action') !== 'submit') {
            return back()->with('status', 'Respuestas guardadas. Puedes continuar más tarde.');
        }

        $exam->update(['status' => 'submitted', 'submitted_at' => now()]);
        $grader->grade($exam);

        return redirect()->route('exams.show', $exam);
    }

    /** Reintenta la corrección de las preguntas abiertas si la IA falló. */
    public function regrade(Request $request, Exam $exam, ExamGrader $grader): RedirectResponse
    {
        $this->authorizeExam($request, $exam, ownerOnly: true);
        abort_unless($exam->status === 'submitted', 409);
        $grader->grade($exam);

        return redirect()->route('exams.show', $exam);
    }

    private function authorizeExam(Request $request, Exam $exam, bool $ownerOnly = false): void
    {
        $user = $request->user();
        $ok = $exam->user_id === $user->id || (! $ownerOnly && $user->isAdmin());
        abort_unless($ok, 404);
    }
}
