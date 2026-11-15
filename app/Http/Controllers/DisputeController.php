<?php

namespace App\Http\Controllers;

use App\Mail\DisputeMail;
use App\Models\AskLog;
use App\Models\Dispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** El árbitro marca «No estoy de acuerdo» en una respuesta propia y explica qué esperaba. */
class DisputeController extends Controller
{
    public function store(Request $request, AskLog $log): RedirectResponse
    {
        abort_unless($log->user_id === $request->user()->id, 404);
        abort_if($log->status === 'error', 422, 'No se puede discutir un error técnico.');

        $data = $request->validate([
            'comment' => 'required|string|min:10|max:1500',
            'suggested_rule' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9.,\s\-]+$/'],
        ], [
            'comment.min' => 'Cuéntanos con algo más de detalle qué esperabas (mínimo 10 caracteres).',
            'suggested_rule.regex' => 'La regla solo puede llevar números y puntos, por ejemplo 10.4.9.',
        ]);

        if (Dispute::where('ask_log_id', $log->id)->where('user_id', $request->user()->id)->exists()) {
            return back()->withErrors(['comment' => 'Ya enviaste una discrepancia sobre esta respuesta.']);
        }

        $dispute = Dispute::create([
            'ask_log_id' => $log->id,
            'user_id' => $request->user()->id,
            'comment' => trim($data['comment']),
            'suggested_rule' => trim((string) ($data['suggested_rule'] ?? '')) ?: null,
        ]);

        $to = config('ipscrules.feedback_email');
        if ($to) {
            try {
                Mail::to($to)->send(new DisputeMail($dispute, route('admin.disputes')));
                $dispute->update(['mailed_at' => now()]);
            } catch (Throwable $e) {
                report($e); // la discrepancia queda guardada y visible en el panel aunque el correo falle
            }
        }

        return redirect()->route('ask', ['r' => $log->id])->with('status', 'Gracias. Hemos recibido tu discrepancia y la revisará un administrador.');
    }
}
