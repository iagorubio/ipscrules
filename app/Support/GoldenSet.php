<?php

namespace App\Support;

use App\Models\Dispute;

/** Preguntas de control para evaluar la búsqueda: las fijas del repositorio y, si se pide, las discrepancias confirmadas. */
final class GoldenSet
{
    /** @return list<array{0:string,1:string,2:list<string>}> */
    public static function cases(bool $withDisputes = false): array
    {
        $cases = require database_path('golden/questions.php');

        if ($withDisputes) {
            foreach (Dispute::with('askLog')->where('status', 'confirmed')->whereNotNull('correct_rule')->get() as $d) {
                $rules = array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $d->correct_rule))));
                if ($rules) {
                    $cases[] = [$d->askLog->discipline, $d->askLog->question, $rules];
                }
            }
        }

        return $cases;
    }
}
