<?php

namespace Tests\Feature;

use App\Models\AskLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\RulebookFixture;
use Tests\TestCase;

class AskTest extends TestCase
{
    use RefreshDatabase, RulebookFixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'test-key']);
        $this->importFixture();
    }

    private function reply(string $text, int $in = 100, int $out = 50): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'usage' => ['input_tokens' => $in, 'output_tokens' => $out]];
    }

    private function fakeAi(string $keywordsJson, ?string $answerJson): void
    {
        $seq = Http::sequence()->push($this->reply($keywordsJson, 30, 10));
        if ($answerJson !== null) {
            $seq->push($this->reply($answerJson, 500, 120));
        }
        Http::fake(['api.anthropic.com/*' => $seq]);
    }

    private function ask(User $u, string $discipline = 'S', string $q = '¿Qué pasa si el disparo cae cerca del competidor?')
    {
        return $this->actingAs($u)->post('/preguntar', ['discipline' => $discipline, 'question' => $q]);
    }

    public function test_answer_with_verified_citation(): void
    {
        $this->fakeAi('{"keywords":["shot","strikes","ground","meters"]}',
            json_encode(['found' => true, 'answer' => 'Es una descalificación salvo excepciones.',
                'citations' => [['rule' => 'Rule 10.4.2', 'quote' => 'except when shooting at a paper or frangible target']]]));
        $user = User::factory()->create();

        $log = tap($this->ask($user))->assertRedirect()->baseResponse;
        $row = AskLog::firstOrFail();

        $this->assertSame('ok', $row->status);
        $this->assertTrue($row->citations[0]['verified']);
        $this->assertSame('10.4.2', $row->citations[0]['rule']);
        $this->assertSame(530, $row->input_tokens);
        $this->assertSame(130, $row->output_tokens);

        $this->actingAs($user)->get('/?r='.$row->id)->assertSee('cita comprobada')->assertSee('Es una descalificación');
    }

    public function test_quote_from_other_discipline_variant_is_not_verified(): void
    {
        // En pistola (H) la variante con "frangible" no existe: la cita inventada no debe verificarse.
        $this->fakeAi('{"keywords":["shot","ground","meters"]}',
            json_encode(['found' => true, 'answer' => 'Respuesta.',
                'citations' => [['rule' => '10.4.2', 'quote' => 'paper or frangible target']]]));

        $this->ask(User::factory()->create(), 'H');
        $row = AskLog::firstOrFail();

        $this->assertSame('unverified', $row->status);
        $this->assertFalse($row->citations[0]['verified']);
    }

    public function test_citation_to_rule_not_in_context_is_unverified(): void
    {
        $this->fakeAi('{"keywords":["muzzle","uprange"]}',
            json_encode(['found' => true, 'answer' => 'Algo.', 'citations' => [['rule' => '99.9.9', 'quote' => 'invented text here']]]));

        $this->ask(User::factory()->create(), 'S', 'muzzle uprange pregunta');
        $row = AskLog::firstOrFail();

        $this->assertSame('unverified', $row->status);
        $this->assertFalse($row->citations[0]['verified']);
    }

    public function test_no_passages_means_no_info_without_second_ai_call(): void
    {
        $this->fakeAi('{"keywords":["zeppelin","submarine"]}', null);

        $this->ask(User::factory()->create());
        $row = AskLog::firstOrFail();

        $this->assertSame('no_info', $row->status);
        Http::assertSentCount(1);
    }

    public function test_model_saying_not_found_is_no_info(): void
    {
        $this->fakeAi('{"keywords":["muzzle"]}', json_encode(['found' => false, 'answer' => 'No consta en el reglamento.', 'citations' => []]));

        $this->ask(User::factory()->create(), 'S', 'muzzle pregunta larga');

        $this->assertSame('no_info', AskLog::firstOrFail()->status);
    }

    public function test_api_failure_is_logged_as_error_without_leaking_details(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response('boom', 500)]);

        $this->ask(User::factory()->create(), 'S', 'muzzle pregunta larga');
        $row = AskLog::firstOrFail();

        $this->assertSame('error', $row->status);
        $this->assertStringNotContainsString('500', $row->answer);
    }

    public function test_monthly_quota_blocks_further_questions(): void
    {
        Http::fake();
        $user = User::factory()->create(['monthly_quota' => 1]);
        AskLog::create(['user_id' => $user->id, 'discipline' => 'S', 'question' => 'previa', 'status' => 'ok']);

        $this->ask($user)->assertSessionHasErrors('question');
        Http::assertNothingSent();
        $this->assertDatabaseCount('ask_logs', 1);
    }

    public function test_global_limit_blocks_questions(): void
    {
        Http::fake();
        config(['ipscrules.global_monthly_limit' => 1]);
        $other = User::factory()->create();
        AskLog::create(['user_id' => $other->id, 'discipline' => 'S', 'question' => 'previa', 'status' => 'ok']);

        $this->ask(User::factory()->create())->assertSessionHasErrors('question');
        Http::assertNothingSent();
    }

    public function test_user_cannot_read_someone_elses_answer(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $log = AskLog::create(['user_id' => $a->id, 'discipline' => 'S', 'question' => 'secreta', 'answer' => 'respuesta privada', 'status' => 'ok']);

        $this->actingAs($b)->get('/?r='.$log->id)->assertDontSee('respuesta privada');
    }

    public function test_invalid_discipline_is_rejected(): void
    {
        Http::fake();
        $this->ask(User::factory()->create(), 'X')->assertSessionHasErrors('discipline');
        Http::assertNothingSent();
    }
}
