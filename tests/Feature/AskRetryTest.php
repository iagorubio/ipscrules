<?php

namespace Tests\Feature;

use App\Models\AskLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\RulebookFixture;
use Tests\TestCase;

class AskRetryTest extends TestCase
{
    use RefreshDatabase, RulebookFixture;

    private function reply(string $text): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]];
    }

    public function test_truncated_json_is_retried_once(): void
    {
        config(['services.anthropic.key' => 'k']);
        $this->importFixture();
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push($this->reply('{"keywords":["muzzle","uprange"]}'))
            ->push($this->reply('{"found": true, "answer": "Respuesta cortada sin cerrar'))   // truncada
            ->push($this->reply(json_encode(['found' => true, 'answer' => 'Descalificación.',
                'citations' => [['rule' => '10.5.2', 'quote' => 'point uprange will result in disqualification']]])),
            )]);

        $this->actingAs(User::factory()->create())->post('/preguntar', ['discipline' => 'S', 'question' => 'muzzle uprange pregunta']);

        $log = AskLog::firstOrFail();
        $this->assertSame('ok', $log->status);
        $this->assertSame(3, count(Http::recorded()));
    }

    public function test_two_invalid_answers_end_as_error(): void
    {
        config(['services.anthropic.key' => 'k']);
        $this->importFixture();
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push($this->reply('{"keywords":["muzzle","uprange"]}'))
            ->push($this->reply('texto sin json'))
            ->push($this->reply('otra vez sin json'))]);

        $this->actingAs(User::factory()->create())->post('/preguntar', ['discipline' => 'S', 'question' => 'muzzle uprange pregunta']);

        $this->assertSame('error', AskLog::firstOrFail()->status);
    }
}
