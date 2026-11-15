<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamQuestion;
use App\Services\QuestionGenerator;
use App\Support\Disciplines;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Banco de preguntas: generar con IA, revisar, editar y aprobar. */
class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'draft');
        $discipline = $request->query('discipline');

        $questions = ExamQuestion::query()
            ->when(in_array($status, ['draft', 'approved', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->when($discipline && Disciplines::valid($discipline), fn ($q) => $q->where('discipline', $discipline))
            ->latest('id')->limit(100)->get();

        $counts = ExamQuestion::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.questions', compact('questions', 'status', 'discipline', 'counts'));
    }

    public function generate(Request $request, QuestionGenerator $generator): RedirectResponse
    {
        $data = $request->validate([
            'discipline' => ['required', Rule::in(Disciplines::codes())],
            'section' => ['nullable', 'regex:/^\d{1,2}(\.\d{1,3}){0,3}$/'],
            'count' => 'required|integer|min:1|max:10',
            'type' => ['required', Rule::in(array_keys(ExamQuestion::TYPES))],
            'difficulty' => ['required', Rule::in(array_keys(ExamQuestion::DIFFICULTIES))],
        ]);

        try {
            $r = $generator->generate($request->user(), $data['discipline'], $data['section'] ?: null, (int) $data['count'], $data['type'], $data['difficulty']);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['count' => 'No se pudieron generar preguntas: '.$e->getMessage()]);
        }

        $msg = "Generadas {$r['created']} preguntas en borrador";
        if ($r['discarded']) {
            $msg .= " ({$r['discarded']} descartadas por no poder verificar su cita o por estar repetidas)";
        }

        return redirect()->route('admin.questions', ['status' => 'draft'])->with('status', $msg.'.');
    }

    public function edit(ExamQuestion $question): View
    {
        return view('admin.question-edit', ['q' => $question]);
    }

    public function update(Request $request, ExamQuestion $question): RedirectResponse
    {
        $rules = ['statement' => 'required|string|min:10|max:1000', 'explanation' => 'nullable|string|max:1000'];
        if ($question->isTest()) {
            $rules += ['options' => 'required|array|size:4', 'options.*' => 'required|string|max:400', 'correct_index' => 'required|integer|between:0,3'];
        } else {
            $rules += ['key_points' => 'required|string|max:1500', 'model_answer' => 'required|string|max:1500'];
        }
        $data = $request->validate($rules);

        if ($question->isTest()) {
            $data['options'] = array_values($data['options']);
        } else {
            $data['key_points'] = array_values(array_filter(array_map('trim', preg_split('/\R/', $data['key_points']))));
        }

        $question->update($data);

        return redirect()->route('admin.questions', ['status' => $question->status])->with('status', 'Pregunta actualizada.');
    }

    public function setStatus(Request $request, ExamQuestion $question): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'approved', 'rejected'])]]);
        $question->update(['status' => $data['status'], 'approved_by' => $data['status'] === 'approved' ? $request->user()->id : null]);

        return back()->with('status', 'Estado actualizado.');
    }

    public function destroy(ExamQuestion $question): RedirectResponse
    {
        $question->delete();

        return back()->with('status', 'Pregunta eliminada.');
    }
}
