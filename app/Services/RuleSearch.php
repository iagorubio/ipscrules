<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Búsqueda de texto completo (FTS5) sobre el reglamento, siempre filtrada por disciplina:
 * solo devuelve el texto común y las variantes que incluyen la letra de la disciplina.
 */
class RuleSearch
{
    private const STOPWORDS = [
        'the', 'and', 'for', 'are', 'was', 'with', 'that', 'this', 'from', 'what', 'when', 'which', 'can', 'may',
        'must', 'not', 'any', 'all', 'has', 'have', 'been', 'does', 'shall', 'will', 'rule', 'rules', 'should',
    ];

    /**
     * @return list<array{ref:string,kind:string,disciplines:string,title:string,text:string}>
     */
    public function search(string $keywords, string $discipline, int $limit = 8): array
    {
        $match = $this->matchQuery($keywords);
        if ($match === null) {
            return [];
        }

        $rows = DB::select(
            "SELECT ref, kind, disciplines, title, body
             FROM rule_search
             WHERE rule_search MATCH ? AND disciplines LIKE ?
             ORDER BY bm25(rule_search, 0, 0, 0, 2.0, 1.0)
             LIMIT ?",
            [$match, '%'.$discipline.'%', $limit]
        );

        return array_map(fn ($r) => $this->row($r), $rows);
    }

    /**
     * Pasajes de reglas concretas (p. ej. si el árbitro escribe "regla 10.4.2").
     *
     * @param  list<string>  $numbers
     * @return list<array{ref:string,kind:string,disciplines:string,title:string,text:string}>
     */
    public function byNumber(array $numbers, string $discipline): array
    {
        if (! $numbers) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($numbers), '?'));
        $rows = DB::select(
            "SELECT ref, kind, disciplines, title, body FROM rule_search
             WHERE kind = 'rule' AND ref IN ($marks) AND disciplines LIKE ?",
            [...$numbers, '%'.$discipline.'%']
        );

        return array_map(fn ($r) => $this->row($r), $rows);
    }

    private function row(object $r): array
    {
        return ['ref' => $r->ref, 'kind' => $r->kind, 'disciplines' => $r->disciplines, 'title' => $r->title, 'text' => $r->body];
    }

    /** Construye una consulta FTS5 segura (cada término entre comillas, unidos por OR). */
    private function matchQuery(string $keywords): ?string
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($keywords), -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_unique(array_filter(
            $tokens,
            fn ($t) => mb_strlen($t) >= 3 && ! in_array($t, self::STOPWORDS, true)
        ));
        if (! $tokens) {
            return null;
        }

        return implode(' OR ', array_map(fn ($t) => '"'.$t.'"', array_slice($tokens, 0, 16)));
    }
}
