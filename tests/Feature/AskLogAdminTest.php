<?php

namespace Tests\Feature;

use App\Models\AskLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AskLogAdminTest extends TestCase
{
    use RefreshDatabase;

    private function log(User $u, string $status, string $q): AskLog
    {
        return AskLog::create(['user_id' => $u->id, 'discipline' => 'S', 'question' => $q, 'answer' => 'resp '.$q, 'status' => $status,
            'citations' => [['rule' => '10.4.9', 'quote' => 'a shot fired using slug ammunition', 'verified' => $status === 'ok']]]);
    }

    public function test_admin_sees_problem_queries_by_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $u = User::factory()->create(['name' => 'Árbitra Uno']);
        $this->log($u, 'ok', 'pregunta bien respondida');
        $this->log($u, 'no_info', 'pregunta sin informacion');
        $this->log($u, 'unverified', 'pregunta sin cita verificada');

        $page = $this->actingAs($admin)->get('/admin/consultas')->assertOk();
        $page->assertSee('pregunta sin informacion')->assertSee('pregunta sin cita verificada')->assertDontSee('pregunta bien respondida')->assertSee('Árbitra Uno');

        $this->actingAs($admin)->get('/admin/consultas?status=ok')->assertSee('pregunta bien respondida')->assertDontSee('pregunta sin informacion');
        $this->actingAs($admin)->get('/admin/consultas?status=inventado')->assertOk()->assertSee('pregunta sin informacion'); // estado desconocido: vuelve al valor por defecto
    }

    public function test_arbitro_cannot_read_the_query_log(): void
    {
        $user = User::factory()->create();
        $this->log($user, 'no_info', 'algo');

        $this->actingAs($user)->get('/admin/consultas')->assertForbidden();
    }
}
