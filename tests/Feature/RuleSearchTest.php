<?php

namespace Tests\Feature;

use App\Services\RuleSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RulebookFixture;
use Tests\TestCase;

class RuleSearchTest extends TestCase
{
    use RefreshDatabase, RulebookFixture;

    public function test_import_counts_and_index(): void
    {
        $this->importFixture();

        $this->assertDatabaseCount('rules', 4);
        $this->assertDatabaseCount('rule_variants', 4);
        $this->assertDatabaseHas('glossary_terms', ['term' => 'Berm']);
        // 4 variantes + 1 glosario + 1 trozo de apéndice (dos párrafos que caben en uno)
        $this->assertSame(6, \DB::table('rule_search')->count());
    }

    public function test_search_returns_only_the_variant_for_the_discipline(): void
    {
        $this->importFixture();
        $search = app(RuleSearch::class);

        $handgun = $search->search('shot strikes ground meters', 'H');
        $shotgun = $search->search('shot strikes ground meters', 'S');

        $this->assertSame('10.4.2', $handgun[0]['ref']);
        $this->assertStringNotContainsString('frangible', $handgun[0]['text']);
        $this->assertSame('10.4.2', $shotgun[0]['ref']);
        $this->assertStringContainsString('frangible', $shotgun[0]['text']);
    }

    public function test_discipline_specific_appendix_is_hidden_from_others(): void
    {
        $this->importFixture();
        $search = app(RuleSearch::class);

        $this->assertNotEmpty($search->search('power factor', 'S'));
        $this->assertEmpty($search->search('power factor', 'H'));
    }

    public function test_by_number_and_hostile_query_do_not_break_fts(): void
    {
        $this->importFixture();
        $search = app(RuleSearch::class);

        $this->assertSame('10.5.2', $search->byNumber(['10.5.2'], 'R')[0]['ref']);
        // Una consulta con sintaxis FTS5 hostil no debe lanzar excepción ni tocar las tablas
        $this->assertIsArray($search->search('"; DROP TABLE rules; -- ( ) * NEAR AND OR', 'H'));
        $this->assertDatabaseCount('rules', 4);
        $this->assertSame([], $search->search('a b', 'H')); // palabras demasiado cortas
    }

    public function test_reimport_replaces_instead_of_duplicating(): void
    {
        $this->importFixture();
        $this->importFixture();

        $this->assertDatabaseCount('rulebooks', 1);
        $this->assertDatabaseCount('rules', 4);
        $this->assertSame(6, \DB::table('rule_search')->count());
    }
}
