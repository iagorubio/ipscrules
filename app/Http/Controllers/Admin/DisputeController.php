<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Revisión de las discrepancias de los árbitros con las respuestas de la IA. */
class DisputeController extends Controller
{
    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('status'), Dispute::STATUSES) ? $request->query('status') : 'open';

        return view('admin.disputes', [
            'disputes' => Dispute::with('askLog', 'user')->where('status', $status)->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => Dispute::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function update(Request $request, Dispute $dispute): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Dispute::STATUSES))],
            'correct_rule' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9.,\s\-]+$/'],
            'admin_note' => 'nullable|string|max:1000',
        ]);

        if ($data['status'] === 'confirmed' && blank($data['correct_rule'] ?? null)) {
            $data['correct_rule'] = $dispute->suggested_rule; // por defecto, la que propuso el árbitro
        }

        $dispute->update([
            'status' => $data['status'],
            'correct_rule' => trim((string) ($data['correct_rule'] ?? '')) ?: null,
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        return back()->with('status', 'Discrepancia actualizada.');
    }

    /** Casos confirmados, uno por línea (JSONL), para compartir o usar como ejemplos de evaluación. */
    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            Dispute::with('askLog')->where('status', 'confirmed')->orderBy('id')->each(function (Dispute $d) {
                echo json_encode([
                    'discipline' => $d->askLog->discipline,
                    'question' => $d->askLog->question,
                    'ai_answer' => $d->askLog->answer,
                    'ai_citations' => collect($d->askLog->citations ?? [])->map(fn ($c) => ['rule' => $c['rule'], 'quote' => $c['quote'], 'verified' => $c['verified']])->all(),
                    'referee_comment' => $d->comment,
                    'correct_rule' => $d->correct_rule,
                    'admin_note' => $d->admin_note,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
            });
        }, 'discrepancias-confirmadas.jsonl', ['Content-Type' => 'application/x-ndjson; charset=utf-8']);
    }
}
