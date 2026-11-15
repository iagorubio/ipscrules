<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RuleAssistant;
use Illuminate\Console\Command;

class EvaluateRetrieval extends Command
{
    protected $signature = 'reglamento:evaluar {--respuesta : Además de la búsqueda, genera la respuesta completa y comprueba las citas} {--detalle : Muestra los pasajes recuperados}';

    protected $description = 'Mide con preguntas de control si la búsqueda (y la respuesta) encuentra la regla correcta';

    public function handle(): int
    {
        $cases = require database_path('golden/questions.php');
        $user = User::first() ?? new User(['id' => 0]);
        $full = $this->option('respuesta');
        $found = $answered = 0;
        $tokens = ['in' => 0, 'out' => 0];

        $this->line(sprintf('%-3s %-4s %-9s %s', '#', 'Disc', 'Búsqueda', 'Pregunta'.($full ? ' → respuesta' : '')));
        foreach ($cases as $i => [$discipline, $question, $expected]) {
            $assistant = app(RuleAssistant::class)->withoutLogging();
            $t = ['in' => 0, 'out' => 0];
            $r = $assistant->retrieve($question, $discipline, $t);
            $refs = array_column($r['passages'], 'ref');
            $pos = null;
            foreach ($expected as $e) {
                $p = array_search($e, $refs, true);
                if ($p !== false && ($pos === null || $p < $pos)) {
                    $pos = $p;
                }
            }
            $found += $pos !== null ? 1 : 0;
            $line = sprintf('%-3d %-4s %-9s %s', $i + 1, $discipline, $pos === null ? 'NO' : 'sí (#'.($pos + 1).')', mb_substr($question, 0, 70));

            if ($full) {
                $log = app(RuleAssistant::class)->withoutLogging()->ask($user, $discipline, $question);
                $cited = collect($log->citations ?? [])->where('verified', true)->pluck('rule')->all();
                $ok = $log->status === 'ok' && array_intersect($expected, $cited);
                $answered += $ok ? 1 : 0;
                $line .= '  → '.($ok ? 'OK' : 'FALLA ['.$log->status.'; cita: '.(implode(',', $cited) ?: '—').']');
                $tokens['in'] += $log->input_tokens;
                $tokens['out'] += $log->output_tokens;
            }
            $tokens['in'] += $t['in'];
            $tokens['out'] += $t['out'];
            $this->line($line);
            if ($this->option('detalle')) {
                $this->line('      palabras: '.$r['keywords']);
                $this->line('      pasajes:  '.implode(' ', $refs).'   (esperada: '.implode(',', $expected).')');
            }
        }

        $n = count($cases);
        $this->newLine();
        $this->info("Búsqueda: la regla esperada está entre los pasajes en {$found} de {$n} preguntas.");
        if ($full) {
            $this->info("Respuesta: correcta con cita verificada en {$answered} de {$n} preguntas.");
        }
        $this->line("Tokens: {$tokens['in']} entrada / {$tokens['out']} salida.");

        return self::SUCCESS;
    }
}
