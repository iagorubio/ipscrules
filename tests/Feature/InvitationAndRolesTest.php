<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationAndRolesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_arbitro_cannot_open_admin_pages(): void
    {
        $arbitro = User::factory()->create(['role' => 'arbitro']);

        $this->actingAs($arbitro)->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($arbitro)->get('/admin/reglamento')->assertForbidden();
        $this->actingAs($arbitro)->post('/admin/usuarios/invitar', ['email' => 'x@example.com', 'role' => 'admin'])->assertForbidden();
    }

    public function test_admin_invites_and_invitee_creates_account(): void
    {
        $response = $this->actingAs($this->admin())
            ->post('/admin/usuarios/invitar', ['email' => 'Nuevo@Example.com', 'role' => 'arbitro']);

        $link = session('invite_link');
        $this->assertNotNull($link);
        $response->assertRedirect();

        // El token no se guarda en claro
        $this->assertDatabaseMissing('invitations', ['token_hash' => basename($link)]);

        auth()->logout();
        $this->get($link)->assertOk()->assertSee('nuevo@example.com');

        $this->post($link, ['name' => 'Ana Árbitra', 'password' => 'una-clave-larga-1', 'password_confirmation' => 'una-clave-larga-1'])
            ->assertRedirect(route('ask'));

        $user = User::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertSame('arbitro', $user->role);
        $this->assertSame(60, $user->monthly_quota);
        $this->assertAuthenticatedAs($user);

        // El enlace no sirve dos veces
        auth()->logout();
        $this->get($link)->assertSee('Invitación no válida');
    }

    public function test_weak_password_is_rejected(): void
    {
        [, $token] = Invitation::issue('a@example.com', 'arbitro', null);

        $this->post(route('invitation.accept', $token), ['name' => 'A', 'password' => 'corta', 'password_confirmation' => 'corta'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_invitation_is_not_valid(): void
    {
        [$invitation, $token] = Invitation::issue('a@example.com', 'arbitro', null);
        $invitation->update(['expires_at' => now()->subMinute()]);

        $this->get(route('invitation.show', $token))->assertSee('Invitación no válida');
        $this->post(route('invitation.accept', $token), ['name' => 'A', 'password' => 'una-clave-larga-1', 'password_confirmation' => 'una-clave-larga-1'])
            ->assertNotFound();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'off@example.com', 'password' => 'una-clave-larga-1', 'is_active' => false]);

        $this->post('/entrar', ['email' => 'off@example.com', 'password' => 'una-clave-larga-1'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/admin/usuarios/{$admin->id}", ['role' => 'arbitro', 'monthly_quota' => 10]);

        $admin->refresh();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertSame(10, $admin->monthly_quota);
    }

    public function test_admin_invite_command_prints_link(): void
    {
        $this->artisan('admin:invitar', ['email' => 'jefe@example.com'])
            ->expectsOutputToContain('/invitacion/')
            ->assertSuccessful();
        $this->assertDatabaseHas('invitations', ['email' => 'jefe@example.com', 'role' => 'admin']);
    }
}
