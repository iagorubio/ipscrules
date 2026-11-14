<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RulebookFixture;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use RefreshDatabase, RulebookFixture;

    public function test_all_pages_render(): void
    {
        $this->importFixture();
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Jefe']);
        User::factory()->create(['name' => 'Persona Árbitra']);
        Invitation::issue('pendiente@example.com', 'arbitro', $admin->id);

        $this->get('/entrar')->assertOk()->assertSee('Entrar');
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Consulta al reglamento')->assertSee('Escopeta');
        $this->actingAs($admin)->get('/admin/usuarios')->assertOk()->assertSee('pendiente@example.com')->assertSee('Persona Árbitra');
        $this->actingAs($admin)->get('/admin/reglamento')->assertOk()->assertSee('Test 2026');
    }

    public function test_rulebook_page_without_import_explains_how_to_load_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/reglamento')->assertOk()->assertSee('reglamento:importar');
    }

    public function test_arbitro_sees_menu_without_admin_links(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertDontSee('/admin/usuarios');
    }
}
