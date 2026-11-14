<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rulebook;
use App\Support\Disciplines;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RulebookController extends Controller
{
    public function index(): View
    {
        $book = Rulebook::where('active', true)->first();
        $coverage = [];
        if ($book) {
            foreach (Disciplines::codes() as $code) {
                $coverage[$code] = DB::table('rule_variants')
                    ->join('rules', 'rules.id', '=', 'rule_variants.rule_id')
                    ->where('rules.rulebook_id', $book->id)
                    ->where('rules.depth', '>=', 3)
                    ->where('rule_variants.disciplines', 'like', "%{$code}%")
                    ->distinct()->count('rules.id');
            }
        }

        return view('admin.rulebook', [
            'book' => $book,
            'counts' => $book ? [
                'rules' => DB::table('rules')->where('rulebook_id', $book->id)->count(),
                'glossary' => DB::table('glossary_terms')->where('rulebook_id', $book->id)->count(),
                'appendices' => DB::table('appendices')->where('rulebook_id', $book->id)->count(),
                'indexed' => DB::table('rule_search')->count(),
            ] : null,
            'coverage' => $coverage,
        ]);
    }
}
