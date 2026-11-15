<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AskLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Registro de consultas: sirve para detectar preguntas mal respondidas y mejorar la búsqueda. */
class AskLogController extends Controller
{
    public const STATUSES = [
        'problems' => 'Con problemas (sin info, sin verificar, error)',
        'no_info' => 'Sin información',
        'unverified' => 'Sin cita verificada',
        'error' => 'Error',
        'ok' => 'Correctas',
    ];

    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('status'), self::STATUSES) ? $request->query('status') : 'problems';

        $logs = AskLog::with('user')
            ->when($status === 'problems', fn ($q) => $q->whereIn('status', ['no_info', 'unverified', 'error']))
            ->when($status !== 'problems', fn ($q) => $q->where('status', $status))
            ->latest('id')->paginate(30)->withQueryString();

        $counts = AskLog::where('created_at', '>=', now()->subDays(30))->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.ask-logs', ['logs' => $logs, 'status' => $status, 'statuses' => self::STATUSES, 'counts' => $counts]);
    }
}
