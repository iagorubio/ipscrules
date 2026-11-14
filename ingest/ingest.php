<?php
declare(strict_types=1);

/**
 * Prototipo de ingesta del reglamento IPSC combinado (6 disciplinas).
 *
 * Uso: php ingest.php <texto.txt> <carpeta-salida>
 * donde texto.txt sale de: pdftotext -enc UTF-8 -layout <pdf> texto.txt
 *
 * Produce: rules.json, appendices.json, glossary.json, report.md
 */

const DISCIPLINES = ['H' => 'Handgun', 'R' => 'Rifle', 'S' => 'Shotgun', 'M' => 'Mini Rifle', 'P' => 'PCC', 'T' => '.22LR Handgun'];
const ALL = ['H', 'R', 'S', 'M', 'P', 'T'];
const FOOTER = '/^IPSC Combined Competition Rules – 6 Disciplines, January 2026 Edition\s+(\S+)\s*$/u';

if ($argc < 3) {
    fwrite(STDERR, "Uso: php ingest.php <texto.txt> <carpeta-salida>\n");
    exit(1);
}
$lines = preg_split('/\R/u', (string) file_get_contents($argv[1]));
$out = rtrim($argv[2], '/\\');
if (!is_dir($out)) {
    mkdir($out, 0777, true);
}

/** Convierte "HRSP" en ['H','R','S','P'] (sin letras inválidas). */
function codes(string $c): array
{
    $r = [];
    foreach (str_split($c) as $ch) {
        if (isset(DISCIPLINES[$ch])) {
            $r[$ch] = $ch;
        }
    }
    return array_values($r);
}

function norm(string $s): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $s));
}

// ---- 1. Límites del cuerpo ------------------------------------------------
$bodyStart = $indexStart = null;
foreach ($lines as $i => $l) {
    if ($bodyStart === null && preg_match('/^\s*CHAPTER 1: Course Design\s*$/u', $l)) {
        $bodyStart = $i;
    }
    if ($indexStart === null && $bodyStart !== null && preg_match('/^\s*INDEX\s*$/u', $l)) {
        $indexStart = $i;
    }
}
if ($bodyStart === null || $indexStart === null) {
    fwrite(STDERR, "No se encontraron los límites del cuerpo.\n");
    exit(2);
}

// ---- 2. Parseo línea a línea ----------------------------------------------
$rules = [];          // número => regla
$order = [];          // orden de aparición
$appendices = [];
$anomalies = [];      // [tipo, detalle]
$chapter = 0;
$chapterTitle = '';
$page = 1;
$cur = null;          // número de la regla actual
$lastChild = [];
$blankPrev = true;
$app = null;          // apéndice actual
$mode = 'chapters';

$newRule = function (string $num, array $codes, string $text, bool $flagStacked = false) use (&$rules, &$order, &$cur, &$chapter, &$chapterTitle, &$page, &$anomalies) {
    if (isset($rules[$num])) {
        $anomalies[] = ['duplicado', "La regla $num aparece más de una vez (pág. $page)."];
        $num .= '#dup' . count($order);
    }
    $parts = explode('.', $num);
    $rules[$num] = [
        'number' => $num,
        'depth' => count($parts),
        'chapter' => $chapter,
        'chapter_title' => $chapterTitle,
        'parent' => count($parts) > 1 ? implode('.', array_slice($parts, 0, -1)) : null,
        'page' => $page,
        'variants' => [['codes' => $codes, 'paras' => $text === '' ? [] : [$text]]],
        'flags' => $flagStacked ? ['numeración apilada reconstruida'] : [],
    ];
    $order[] = $num;
    $cur = $num;
};

$addText = function (string $text, bool $newPara) use (&$rules, &$cur) {
    if ($cur === null || $text === '') {
        return;
    }
    $v = count($rules[$cur]['variants']) - 1;
    $p = &$rules[$cur]['variants'][$v]['paras'];
    if ($newPara || !$p) {
        $p[] = $text;
    } else {
        $p[count($p) - 1] .= ' ' . $text;
    }
};

for ($i = $bodyStart; $i < $indexStart; $i++) {
    $raw = rtrim(str_replace("\f", '', $lines[$i]));

    if (preg_match(FOOTER, $raw, $m)) {
        $page = ctype_digit($m[1]) ? (int) $m[1] + 1 : $page;
        $blankPrev = true;
        continue;
    }
    if (trim($raw) === '') {
        $blankPrev = true;
        continue;
    }
    $t = ltrim($raw);

    // Cabecera de apéndice
    if (preg_match('/^APPENDIX ([A-F]\d+[a-c]?):\s*(.+)$/u', $t, $m)) {
        $mode = 'appendices';
        preg_match('/\[([A-Z]{1,6})\]\s*$/u', $m[2], $cm);
        $app = [
            'id' => $m[1],
            'title' => norm(preg_replace('/\s*\[[A-Z]{1,6}\]\s*$/u', '', $m[2])),
            'disciplines' => isset($cm[1]) ? codes($cm[1]) : ALL,
            'page' => $page,
            'lines' => [],
        ];
        $appendices[] = &$app;
        unset($app);
        $app = &$appendices[count($appendices) - 1];
        $blankPrev = true;
        continue;
    }
    if ($mode === 'appendices') {
        $app['lines'][] = $raw;
        $blankPrev = false;
        continue;
    }

    // Cabecera de capítulo
    if (preg_match('/^CHAPTER (\d+):\s*(.+)$/u', $t, $m)) {
        $chapter = (int) $m[1];
        $chapterTitle = norm($m[2]);
        $cur = null;
        $blankPrev = true;
        continue;
    }

    $indent = strlen($raw) - strlen($t);

    // Inicio de regla numerada
    if ($indent <= 3 && preg_match('/^(\d{1,2}(?:\.\d{1,3})+)\.?(?:\s+|$)(?:\[([A-Z]{1,6})\]\s*)?(.*)$/u', $t, $m)
        && (int) explode('.', $m[1])[0] === $chapter) {
        $num = $m[1];
        $depth = substr_count($num, '.') + 1;
        $c = $m[2] !== '' ? codes($m[2]) : [];
        $text = norm($m[3]);

        // Plausibilidad: debe ser un hijo nuevo posterior al último de su padre (o una
        // variante con código de una regla ya existente); si no, es una referencia en mitad de párrafo.
        $parts = explode('.', $num);
        $idx = (int) array_pop($parts);
        $parent = implode('.', $parts);
        $last = $lastChild[$parent] ?? 0;
        if (isset($rules[$num]) && $c) {
            $rules[$num]['variants'][] = ['codes' => $c, 'paras' => $text === '' ? [] : [$text]];
            $cur = $num;
            $blankPrev = false;
            continue;
        }
        if ($idx <= $last || ($depth > 2 && !isset($rules[$parent]))) {
            $addText(norm($t), $blankPrev);
            $blankPrev = false;
            continue;
        }
        $lastChild[$parent] = $idx;
        $newRule($num, $c, $text);
        $blankPrev = false;
        continue;
    }

    // Variante con código de disciplina
    if ($cur !== null && preg_match('/^\[([A-Z]{1,6})\]\s+(.*)$/u', $t, $m)) {
        $rules[$cur]['variants'][] = ['codes' => codes($m[1]), 'paras' => [norm($m[2])]];
        $blankPrev = false;
        continue;
    }

    // Continuación
    $addText(norm($t), $blankPrev);
    $blankPrev = false;
}
unset($app);

// ---- 3. Cierre de reglas ---------------------------------------------------
$final = [];
foreach ($order as $num) {
    $r = $rules[$num];
    $vs = [];
    foreach ($r['variants'] as $v) {
        $txt = norm(implode(' ', $v['paras']));
        $vs[] = [
            'disciplines' => $v['codes'] ?: ALL,
            'tagged' => (bool) $v['codes'],
            'text' => $txt,
            'paras' => $v['paras'],
        ];
    }
    // Si la variante 0 está vacía y es la sola sin etiqueta (la regla es sólo un título), se conserva sin texto.
    $r['variants'] = $vs;
    $allText = norm(implode(' ', array_column($vs, 'text')));
    if ($allText === '' && $r['depth'] >= 3) {
        $r['flags'][] = 'regla vacía';
        $anomalies[] = ['regla vacía', "{$r['number']} (pág. {$r['page']})"];
    }
    if (count($vs) > 1 && !$vs[0]['tagged'] && $vs[0]['text'] === '') {
        array_shift($r['variants']);
    }
    // Título: texto antes de " – " en reglas de profundidad ≤ 3 con frase corta
    $r['title'] = null;
    $first = $r['variants'][0]['text'] ?? '';
    if ($r['depth'] === 2) {
        $r['title'] = $first;
    } elseif ($r['depth'] === 3 && preg_match('/^([^–]{2,60}?) – /u', $first, $m)) {
        $r['title'] = $m[1];
    }
    $final[$num] = $r;
}

// ---- 4. Glosario (12.5) ----------------------------------------------------
$glossary = [];
if (isset($final['12.5'])) {
    // El glosario es una tabla "Término ........ [códigos] definición"
    $g = norm(implode(' ', array_column($final['12.5']['variants'], 'text')));
    $g = preg_replace('/^Glossary Throughout these rules, the following definitions apply:\s*/u', '', $g);
    $term = '[A-Z0-9][^.]{0,70}?(?:\s\.\s?[A-Za-z]+\.)?';
    preg_match_all("/($term)\s*\.{4,}\s*(.*?)(?=(?:$term)\s*\.{4,}|\$)/su", $g, $mm, PREG_SET_ORDER);
    foreach ($mm as $m) {
        $def = norm($m[2]);
        $codes = [];
        if (preg_match('/^\[([A-Z]{1,6})\]\s*(.*)$/su', $def, $cm)) {
            $codes = codes($cm[1]);
            $def = $cm[2];
        }
        $glossary[] = ['term' => norm($m[1]), 'definition' => $def, 'disciplines' => $codes ?: ALL];
    }
}

// ---- 5. Apéndices ----------------------------------------------------------
$appOut = [];
foreach ($appendices as $a) {
    $a['text'] = trim(implode("\n", array_map('rtrim', $a['lines'])));
    unset($a['lines']);
    $appOut[] = $a;
}

// ---- 6. Comprobaciones -----------------------------------------------------
// 6a. Huecos y orden en la numeración
$byParent = [];
foreach ($final as $num => $r) {
    if (str_contains($num, '#dup')) {
        continue;
    }
    $byParent[$r['parent'] ?? 'root'][] = (int) substr($num, strrpos($num, '.') + 1);
}
foreach ($byParent as $parent => $nums) {
    if ($parent === 'root') {
        continue;
    }
    $sorted = $nums;
    sort($sorted);
    $max = max($sorted);
    $missing = array_diff(range(1, $max), $sorted);
    // Los hijos de un capítulo (p. ej. 12.x) y de otras reglas deben ser consecutivos desde 1
    if ($missing) {
        $anomalies[] = ['hueco en numeración', "Bajo $parent faltan: " . implode(', ', array_map(fn ($n) => "$parent.$n", $missing))];
    }
    if ($nums !== $sorted) {
        $anomalies[] = ['orden alterado', "Bajo $parent los hijos aparecen en orden distinto: " . implode(',', $nums)];
    }
}

// 6b. Referencias cruzadas
$refs = [];
foreach ($final as $num => $r) {
    $text = implode(' ', array_column($r['variants'], 'text'));
    preg_match_all('/\b(?:Rules?|Sections?)\s+(\d{1,2}(?:\.\d{1,3})+(?:\s*(?:,|and|or|&|to)\s*\d{1,2}(?:\.\d{1,3})+)*)/u', $text, $mm);
    foreach ($mm[1] as $group) {
        preg_match_all('/\d{1,2}(?:\.\d{1,3})+/', $group, $ids);
        foreach ($ids[0] as $id) {
            $refs[$num][$id] = true;
        }
    }
}
$dangling = [];
$refCount = 0;
foreach ($refs as $from => $ids) {
    foreach (array_keys($ids) as $id) {
        $refCount++;
        if (!isset($final[$id])) {
            $dangling[] = "$from → $id";
        }
    }
}
foreach ($final as $num => &$r) {
    $r['refs'] = array_keys($refs[$num] ?? []);
}
unset($r);

// 6c. Cobertura por disciplina
$cover = array_fill_keys(ALL, ['rules' => 0, 'with_variant' => 0]);
foreach ($final as $r) {
    if ($r['depth'] < 3) {
        continue;
    }
    foreach (ALL as $d) {
        $has = false;
        $tagged = false;
        foreach ($r['variants'] as $v) {
            if (in_array($d, $v['disciplines'], true)) {
                $has = true;
                if ($v['tagged']) {
                    $tagged = true;
                }
            }
        }
        if ($has && trim(implode('', array_column($r['variants'], 'text'))) !== '') {
            $cover[$d]['rules']++;
            if ($tagged) {
                $cover[$d]['with_variant']++;
            }
        }
    }
}

// 6d. Reglas con variantes que no cubren todas las disciplinas (p. ej. falta la variante de alguna)
$partial = [];
foreach ($final as $r) {
    $tagged = array_filter($r['variants'], fn ($v) => $v['tagged']);
    if (count($tagged) === count($r['variants']) && $tagged) {
        $cov = [];
        foreach ($r['variants'] as $v) {
            $cov = array_unique(array_merge($cov, $v['disciplines']));
        }
        $miss = array_diff(ALL, $cov);
        if ($miss) {
            $partial[] = $r['number'] . ' (no aplica a ' . implode('', $miss) . ')';
        }
    }
}

// ---- 7. Salida --------------------------------------------------------------
$flagged = array_filter($final, fn ($r) => $r['flags']);
$jf = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
file_put_contents("$out/rules.json", json_encode(array_values(array_map(function ($r) {
    foreach ($r['variants'] as &$v) {
        unset($v['paras']);
    }
    return $r;
}, $final)), $jf));
file_put_contents("$out/appendices.json", json_encode($appOut, $jf));
file_put_contents("$out/glossary.json", json_encode($glossary, $jf));

$byDepth = [];
foreach ($final as $r) {
    $byDepth[$r['depth']] = ($byDepth[$r['depth']] ?? 0) + 1;
}
ksort($byDepth);
$multi = count(array_filter($final, fn ($r) => count($r['variants']) > 1));

$md = "# Informe de ingesta\n\n";
$md .= "- Reglas totales: " . count($final) . ' (por profundidad: ' . implode(', ', array_map(fn ($d, $n) => "nivel $d: $n", array_keys($byDepth), $byDepth)) . ")\n";
$md .= "- Reglas con varias variantes por disciplina: $multi\n";
$md .= "- Apéndices: " . count($appOut) . "\n";
$md .= "- Entradas de glosario: " . count($glossary) . ' (con término detectado: ' . count(array_filter($glossary, fn ($g) => $g['term'])) . ")\n";
$md .= "- Referencias cruzadas: $refCount; sin destino: " . count($dangling) . "\n";
$md .= "- Reglas marcadas para revisión: " . count($flagged) . "\n\n";
$md .= "## Reglas aplicables por disciplina (nivel ≥ 3)\n\n| Disciplina | Reglas con texto | De ellas, con variante propia |\n|---|---|---|\n";
foreach (ALL as $d) {
    $md .= '| ' . DISCIPLINES[$d] . " ($d) | {$cover[$d]['rules']} | {$cover[$d]['with_variant']} |\n";
}
$md .= "\n## Anomalías (" . count($anomalies) . ")\n\n";
$grp = [];
foreach ($anomalies as [$t, $d]) {
    $grp[$t][] = $d;
}
foreach ($grp as $t => $ds) {
    $md .= "### $t (" . count($ds) . ")\n\n";
    foreach (array_slice($ds, 0, 60) as $d) {
        $md .= "- $d\n";
    }
    if (count($ds) > 60) {
        $md .= '- … y ' . (count($ds) - 60) . " más\n";
    }
    $md .= "\n";
}
$md .= "## Reglas reconstruidas por numeración apilada (" . count($flagged) . ")\n\n" . implode(', ', array_keys($flagged)) . "\n\n";
$md .= "## Referencias sin destino (" . count($dangling) . ")\n\n" . implode("\n", array_map(fn ($d) => "- $d", array_slice($dangling, 0, 80))) . "\n\n";
$md .= "## Reglas sólo con variantes etiquetadas que no cubren todas las disciplinas (" . count($partial) . ")\n\n" . implode(', ', array_slice($partial, 0, 80)) . "\n";
file_put_contents("$out/report.md", $md);

echo "OK: " . count($final) . " reglas, " . count($appOut) . " apéndices, " . count($anomalies) . " anomalías. Salida en $out\n";
