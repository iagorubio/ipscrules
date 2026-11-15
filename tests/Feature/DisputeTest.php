<?php

namespace Tests\Feature;

use App\Mail\DisputeMail;
use App\Models\AskLog;
use App\Models\Dispute;
use App\Models\User;
use App\Support\GoldenSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DisputeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ipscrules.feedback_email' => 'admin@example.com']);
    }

    private function log(User $u, string $status = 'ok'): AskLog
    {
        return AskLog::create([
            'user_id' => $u->id, 'discipline' => 'S', 'question' => 'Cual es la penalización por usar slug en birdshot',
            'answer' => 'No consta en el reglamento.', 'status' => $status,
            'citations' => [['rule' => '2.1.12', 'quote' => 'Courses of fire must nominate a single permitted type', 'verified' => true, 'heading' => null, 'text' => null]],
        ]);
    }

    private function form(array $over = []): array
    {
        return $over + ['comment' => 'La regla 10.4.9 dice que es descalificación.', 'suggested_rule' => '10.4.9'];
    }

    public function test_referee_sends_a_dispute_and_admin_gets_the_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Ana Árbitra', 'email' => 'ana@example.com']);
        $log = $this->log($user);

        $this->actingAs($user)->post("/consultas/{$log->id}/discrepancia", $this->form())->assertRedirect(route('ask', ['r' => $log->id]))->assertSessionHas('status');

        $d = Dispute::firstOrFail();
        $this->assertSame('open', $d->status);
        $this->assertSame('10.4.9', $d->suggested_rule);
        $this->assertNotNull($d->mailed_at);

        Mail::assertSent(DisputeMail::class, function (DisputeMail $m) {
            $m->assertSeeInText('Cual es la penalización por usar slug en birdshot');
            $m->assertSeeInText('No consta en el reglamento.');
            $m->assertSeeInText('La regla 10.4.9 dice que es descalificación.');
            $m->assertSeeInText('ana@example.com');
            $m->assertSeeInHtml('2.1.12');

            return $m->hasTo('admin@example.com') && $m->hasReplyTo('ana@example.com');
        });
    }

    public function test_dispute_is_saved_even_if_mail_fails(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp caído'));
        $user = User::factory()->create();
        $log = $this->log($user);

        $this->actingAs($user)->post("/consultas/{$log->id}/discrepancia", $this->form())->assertRedirect();

        $this->assertDatabaseCount('disputes', 1);
        $this->assertNull(Dispute::first()->mailed_at);
    }

    public function test_no_email_configured_still_saves(): void
    {
        Mail::fake();
        config(['ipscrules.feedback_email' => null]);
        $user = User::factory()->create();
        $log = $this->log($user);

        $this->actingAs($user)->post("/consultas/{$log->id}/discrepancia", $this->form());

        $this->assertDatabaseCount('disputes', 1);
        Mail::assertNothingSent();
    }

    public function test_only_one_dispute_per_answer_and_validation(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $log = $this->log($user);

        $this->actingAs($user)->post("/consultas/{$log->id}/discrepancia", $this->form(['comment' => 'corto']))->assertSessionHasErrors('comment');
        $this->actingAs($user)->post("/consultas/{$log->id}/discrepancia", $this->form(['suggested_rule' => '<script>']))->assertSessionHasErrors('suggested_rule');
        $this->assertDatabaseCount('disputes', 0);

        $this->actingAs($user)->post("/consultas/{$log->id}/discrepancia", $this->form())->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/consultas/{$log->id}/discrepancia", $this->form())->assertSessionHasErrors('comment');
        $this->assertDatabaseCount('disputes', 1);
    }

    public function test_cannot_dispute_someone_elses_answer_or_a_technical_error(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->post("/consultas/{$this->log($owner)->id}/discrepancia", $this->form())->assertNotFound();
        $this->actingAs($owner)->post("/consultas/{$this->log($owner, 'error')->id}/discrepancia", $this->form())->assertStatus(422);
        $this->assertDatabaseCount('disputes', 0);
    }

    public function test_answer_page_shows_the_button_and_later_the_status(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $log = $this->log($user);

        $this->actingAs($user)->get('/?r='.$log->id)->assertOk()->assertSee('No estoy de acuerdo con esta respuesta');

        $this->actingAs($user)->post("/consultas/{$log->id}/discrepancia", $this->form());
        $this->actingAs($user)->get('/?r='.$log->id)->assertDontSee('Enviar discrepancia')->assertSee('pendiente de revisión');

        Dispute::first()->update(['status' => 'confirmed', 'admin_note' => 'Tienes razón, lo corregimos.']);
        $this->actingAs($user)->get('/?r='.$log->id)->assertSee('la IA se equivocó')->assertSee('Tienes razón');
    }

    public function test_admin_reviews_confirms_and_exports(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['name' => 'Ana Árbitra']);
        $log = $this->log($user);
        $this->actingAs($user)->post("/consultas/{$log->id}/discrepancia", $this->form());
        $d = Dispute::firstOrFail();

        $this->actingAs($admin)->get('/admin/discrepancias')->assertOk()->assertSee('Ana Árbitra')->assertSee('La regla 10.4.9 dice');

        // Al confirmar sin indicar regla, se usa la propuesta por el árbitro
        $this->actingAs($admin)->patch("/admin/discrepancias/{$d->id}", ['status' => 'confirmed', 'correct_rule' => '', 'admin_note' => 'Correcto'])->assertRedirect();
        $this->assertSame('10.4.9', $d->fresh()->correct_rule);

        $response = $this->actingAs($admin)->get('/admin/discrepancias/exportar')->assertOk();
        $line = json_decode(trim($response->streamedContent()), true);
        $this->assertSame('10.4.9', $line['correct_rule']);
        $this->assertSame('S', $line['discipline']);
        $this->assertStringContainsString('slug', $line['question']);
        $this->assertArrayNotHasKey('email', $line);
        $this->assertStringNotContainsString('Ana', json_encode($line)); // el export no lleva datos personales
    }

    public function test_rejected_and_open_disputes_are_not_exported_nor_used_as_control_cases(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $log = $this->log($user);
        Dispute::create(['ask_log_id' => $log->id, 'user_id' => $user->id, 'comment' => 'comentario suficiente', 'status' => 'rejected']);

        $this->assertSame('', trim($this->actingAs($admin)->get('/admin/discrepancias/exportar')->streamedContent()));
        $this->assertSame(count(GoldenSet::cases()), count(GoldenSet::cases(true)));
    }

    public function test_confirmed_disputes_join_the_golden_set(): void
    {
        $user = User::factory()->create();
        $log = $this->log($user);
        Dispute::create(['ask_log_id' => $log->id, 'user_id' => $user->id, 'comment' => 'comentario suficiente', 'status' => 'confirmed', 'correct_rule' => '10.4.9, 10.4.10']);

        $base = count(GoldenSet::cases());
        $all = GoldenSet::cases(true);

        $this->assertCount($base + 1, $all);
        $this->assertSame(['S', 'Cual es la penalización por usar slug en birdshot', ['10.4.9', '10.4.10']], end($all));
    }

    public function test_only_admin_reaches_the_admin_dispute_pages(): void
    {
        $user = User::factory()->create();
        $d = Dispute::create(['ask_log_id' => $this->log($user)->id, 'user_id' => $user->id, 'comment' => 'comentario suficiente']);

        $this->actingAs($user)->get('/admin/discrepancias')->assertForbidden();
        $this->actingAs($user)->get('/admin/discrepancias/exportar')->assertForbidden();
        $this->actingAs($user)->patch("/admin/discrepancias/{$d->id}", ['status' => 'confirmed'])->assertForbidden();
        $this->assertSame('open', $d->fresh()->status);
    }
}
