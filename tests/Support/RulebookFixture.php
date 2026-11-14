<?php

namespace Tests\Support;

use App\Services\RuleImporter;

/** Mini reglamento de prueba con la misma forma que la salida de ingest/ingest.php. */
trait RulebookFixture
{
    protected function importFixture(): void
    {
        $all = ['H', 'R', 'S', 'M', 'P', 'T'];
        $rules = [
            ['number' => '10', 'depth' => 1, 'chapter' => 10, 'chapter_title' => 'Penalties and Disqualifications', 'title' => null, 'page' => 45, 'variants' => []],
            ['number' => '10.4', 'depth' => 2, 'chapter' => 10, 'chapter_title' => 'Penalties and Disqualifications', 'title' => 'Disqualification – Accidental Discharge', 'page' => 48,
                'variants' => [['disciplines' => $all, 'tagged' => false, 'text' => 'Disqualification – Accidental Discharge']]],
            ['number' => '10.4.2', 'depth' => 3, 'chapter' => 10, 'chapter_title' => 'Penalties and Disqualifications', 'title' => null, 'page' => 48, 'variants' => [
                ['disciplines' => ['H', 'T'], 'tagged' => true, 'text' => 'A shot which strikes the ground within 3 meters of the competitor, except when shooting at a paper target closer than 3 meters to the competitor.'],
                ['disciplines' => ['R', 'S', 'M', 'P'], 'tagged' => true, 'text' => 'A shot which strikes the ground within 3 meters of the competitor, except when shooting at a paper or frangible target closer than 3 meters to the competitor.'],
            ]],
            ['number' => '10.5.2', 'depth' => 3, 'chapter' => 10, 'chapter_title' => 'Penalties and Disqualifications', 'title' => null, 'page' => 49, 'variants' => [
                ['disciplines' => $all, 'tagged' => false, 'text' => 'Allowing the muzzle of a firearm to point uprange will result in disqualification.'],
            ]],
        ];
        $glossary = [['term' => 'Berm', 'definition' => 'A raised structure of sand, soil or other materials used to contain bullets.', 'disciplines' => $all]];
        $appendices = [['id' => 'D8', 'title' => 'Shotgun Divisions', 'disciplines' => ['S'], 'page' => 92, 'text' => "1. Minimum Power Factor 480\n\n2. Minimum caliber 20 gauge"]];

        app(RuleImporter::class)->import('Test 2026', $rules, $appendices, $glossary);
    }
}
