<?php

namespace App\Http\Controllers;

use App\Models\AskLog;
use App\Services\RuleAssistant;
use App\Support\Disciplines;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $current = $request->integer('r') ? $user->askLogs()->find($request->integer('r')) : null;

        return view('ask.index', [
            'current' => $current,
            'history' => $user->askLogs()->latest()->limit(10)->get(),
            'used' => $user->questionsThisMonth(),
            'quota' => $user->monthly_quota,
            'discipline' => $request->session()->get('discipline', $current?->discipline ?? 'H'),
        ]);
    }

    public function store(Request $request, RuleAssistant $assistant): RedirectResponse
    {
        $data = $request->validate([
            'discipline' => ['required', Rule::in(Disciplines::codes())],
            'question' => 'required|string|min:6|max:600',
        ]);
        $user = $request->user();
        $request->session()->put('discipline', $data['discipline']);

        if ($user->questionsThisMonth() >= $user->monthly_quota) {
            return back()->withInput()->withErrors(['question' => 'Has agotado tus preguntas de este mes. Se renuevan el día 1.']);
        }
        $monthTotal = AskLog::where('created_at', '>=', now()->startOfMonth())->count();
        if ($monthTotal >= config('ipscrules.global_monthly_limit')) {
            return back()->withInput()->withErrors(['question' => 'Se ha alcanzado el límite mensual de consultas de la aplicación. Contacta con el administrador.']);
        }

        $log = $assistant->ask($user, $data['discipline'], trim($data['question']));

        return redirect()->route('ask', ['r' => $log->id]);
    }
}
