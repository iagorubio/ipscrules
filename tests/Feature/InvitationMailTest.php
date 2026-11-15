<?php

namespace Tests\Feature;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvitationMailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Jefa de Arbitraje']);
    }

    public function test_invitation_is_emailed_with_a_working_link(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post('/admin/usuarios/invitar', ['email' => 'Nueva@Example.com', 'role' => 'arbitro', 'send_email' => 1])
            ->assertSessionHas('mail_sent');

        Mail::assertSent(InvitationMail::class, function (InvitationMail $m) {
            $this->assertStringContainsString('/invitacion/', $m->link);
            $this->assertSame($m->link, session('invite_link'));

            return $m->hasTo('nueva@example.com') && $m->role === 'arbitro';
        });

        // El enlace del correo permite crear la cuenta
        auth()->logout();
        $link = session('invite_link');
        $this->get($link)->assertOk()->assertSee('nueva@example.com');
    }

    public function test_email_is_optional(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())->post('/admin/usuarios/invitar', ['email' => 'a@example.com', 'role' => 'arbitro'])
            ->assertSessionHas('invite_link')->assertSessionMissing('mail_sent');

        Mail::assertNothingSent();
    }

    public function test_mail_failure_warns_but_keeps_the_link(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp caído'));

        $this->actingAs($this->admin())->post('/admin/usuarios/invitar', ['email' => 'a@example.com', 'role' => 'arbitro', 'send_email' => 1])
            ->assertSessionHas('mail_error')->assertSessionHas('invite_link');

        $this->assertDatabaseHas('invitations', ['email' => 'a@example.com']);
    }

    public function test_resend_creates_a_new_link_and_kills_the_old_one(): void
    {
        Mail::fake();
        $admin = $this->admin();
        [$old, $oldToken] = Invitation::issue('p@example.com', 'arbitro', $admin->id);

        $this->actingAs($admin)->post("/admin/invitaciones/{$old->id}/reenviar")->assertSessionHas('mail_sent');

        $this->assertDatabaseMissing('invitations', ['id' => $old->id]);
        $this->assertNull(Invitation::findValid($oldToken));
        Mail::assertSent(InvitationMail::class, fn ($m) => $m->hasTo('p@example.com') && ! str_contains($m->link, $oldToken));
        $this->assertDatabaseCount('invitations', 1);
    }

    public function test_cannot_resend_an_accepted_invitation(): void
    {
        Mail::fake();
        $admin = $this->admin();
        [$inv] = Invitation::issue('p@example.com', 'arbitro', $admin->id);
        $inv->update(['accepted_at' => now()]);

        $this->actingAs($admin)->post("/admin/invitaciones/{$inv->id}/reenviar")->assertNotFound();
        Mail::assertNothingSent();
    }

    public function test_only_admin_can_send_invitations(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        [$inv] = Invitation::issue('p@example.com', 'arbitro', null);

        $this->actingAs($user)->post('/admin/usuarios/invitar', ['email' => 'x@example.com', 'role' => 'admin', 'send_email' => 1])->assertForbidden();
        $this->actingAs($user)->post("/admin/invitaciones/{$inv->id}/reenviar")->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_mail_content_has_link_in_html_and_text(): void
    {
        $mail = new InvitationMail('https://ipscrules.sigeet.es/invitacion/abc', 'admin', 'Ana', now()->addDays(14));

        $mail->assertSeeInHtml('https://ipscrules.sigeet.es/invitacion/abc');
        $mail->assertSeeInHtml('administrador');
        $mail->assertSeeInText('https://ipscrules.sigeet.es/invitacion/abc');
        $mail->assertSeeInText('Ana te ha invitado');
        $this->assertSame('Invitación a '.config('app.name'), $mail->envelope()->subject);
    }

    public function test_admin_command_can_send_the_email(): void
    {
        Mail::fake();

        $this->artisan('admin:invitar', ['email' => 'jefe@example.com', '--enviar' => true])->expectsOutputToContain('Correo enviado')->assertSuccessful();

        Mail::assertSent(InvitationMail::class, fn ($m) => $m->hasTo('jefe@example.com') && $m->role === 'admin');
    }
}
