<?php

namespace App\Services;

use App\Models\Rulebook;
use Illuminate\Support\Facades\DB;

/**
 * Carga en la base de datos el resultado de la ingesta (ingest/ingest.php):
 * reglas con variantes por disciplina, glosario y apéndices, y reconstruye el índice FTS5.
 * Reemplaza el reglamento anterior: solo hay uno activo.
 */
class RuleImporter
{
    private const APPENDIX_CHUNK = 1500;

    /** @return array{rules:int,variants:int,glossary:int,appendices:int,indexed:int} */
    public function import(string $edition, array $rules, array $appendices, array $glossary): array
    {
        return DB::transaction(function () use ($edition, $rules, $appendices, $glossary) {
            DB::table('rulebooks')->delete(); // cascada a reglas, variantes, glosario y apéndices
            DB::table('rule_search')->delete();

            $book = Rulebook::create(['edition' => $edition, 'active' => true, 'imported_at' => now()]);

            // Títulos de sección (nivel 2) para dar contexto a cada regla
            $sections = [];
            foreach ($rules as $r) {
                if (($r['depth'] ?? 0) === 2) {
                    $sections[$r['number']] = $r['title'] ?: $r['number'];
                }
            }

            $counts = ['rules' => 0, 'variants' => 0, 'glossary' => 0, 'appendices' => 0, 'indexed' => 0];

            foreach ($rules as $r) {
                $ruleId = DB::table('rules')->insertGetId([
                    'rulebook_id' => $book->id,
                    'number' => $r['number'],
                    'depth' => $r['depth'],
                    'chapter' => $r['chapter'],
                    'chapter_title' => $r['chapter_title'] ?? null,
                    'title' => $r['title'] ?? null,
                    'page' => $r['page'] ?? null,
                ]);
                $counts['rules']++;

                $sectionKey = implode('.', array_slice(explode('.', $r['number']), 0, 2));
                $heading = trim(($r['chapter_title'] ?? '').' > '.$sectionKey.' '.($sections[$sectionKey] ?? ''), ' >');

                foreach ($r['variants'] as $v) {
                    $text = trim($v['text'] ?? '');
                    if ($text === '') {
                        continue;
                    }
                    $letters = implode('', $v['disciplines']);
                    DB::table('rule_variants')->insert(['rule_id' => $ruleId, 'disciplines' => $letters, 'text' => $text]);
                    $counts['variants']++;
                    $this->index($r['number'], 'rule', $letters, $heading, $text, $counts);
                }
            }

            foreach ($glossary as $g) {
                if (empty($g['term'])) {
                    continue;
                }
                $letters = implode('', $g['disciplines']);
                DB::table('glossary_terms')->insert([
                    'rulebook_id' => $book->id, 'term' => $g['term'], 'disciplines' => $letters, 'definition' => $g['definition'],
                ]);
                $counts['glossary']++;
                $this->index('Glossary: '.$g['term'], 'glossary', $letters, 'Glossary '.$g['term'], $g['term'].': '.$g['definition'], $counts);
            }

            foreach ($appendices as $a) {
                $letters = implode('', $a['disciplines']);
                DB::table('appendices')->insert([
                    'rulebook_id' => $book->id, 'code' => $a['id'], 'title' => $a['title'],
                    'disciplines' => $letters, 'page' => $a['page'] ?? null, 'text' => $a['text'],
                ]);
                $counts['appendices']++;
                foreach ($this->chunk($a['text']) as $chunk) {
                    $this->index('Appendix '.$a['id'], 'appendix', $letters, 'Appendix '.$a['id'].' '.$a['title'], $chunk, $counts);
                }
            }

            return $counts;
        });
    }

    private function index(string $ref, string $kind, string $letters, string $title, string $body, array &$counts): void
    {
        DB::table('rule_search')->insert([
            'ref' => $ref, 'kind' => $kind, 'disciplines' => $letters, 'title' => $title, 'body' => $body,
        ]);
        $counts['indexed']++;
    }

    /** @return list<string> */
    private function chunk(string $text): array
    {
        $chunks = [];
        $current = '';
        foreach (preg_split('/\n{2,}|\n(?=\s*\d+\.\s)/u', $text) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if ($current !== '' && mb_strlen($current) + mb_strlen($part) > self::APPENDIX_CHUNK) {
                $chunks[] = $current;
                $current = '';
            }
            $current .= ($current === '' ? '' : "\n").$part;
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
