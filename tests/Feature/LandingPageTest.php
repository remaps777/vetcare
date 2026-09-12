<?php

namespace Tests\Feature;

use App\Models\ClinicSetting;
use App\Models\User;
use App\Services\RecordVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_connects_owner_worker_and_login_flows(): void
    {
        $response = $this->get('/');
        $response
            ->assertOk()
            ->assertSee('Cuidamos a tu mejor amigo')
            ->assertSee(route('login'), false)
            ->assertSee(route('register.owner'), false)
            ->assertSee(route('register.worker'), false)
            ->assertSee('Registrar mi cuenta')
            ->assertSee('Enviar solicitud de acceso')
            ->assertDontSee('Registrarme como administrador');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_configured_contact_and_social_links_are_rendered_safely(): void
    {
        ClinicSetting::query()->firstOrFail()->update([
            'name' => 'Clínica Patitas',
            'phone' => '01 555 0199',
            'email' => 'contacto@clinicapatitas.test',
            'address' => 'Av. Veterinaria 123, Lima',
            'facebook_url' => 'https://facebook.com/clinicapatitas',
            'instagram_url' => 'https://instagram.com/clinicapatitas',
            'tiktok_url' => 'https://www.tiktok.com/@clinicapatitas',
            'whatsapp_number' => '51999999999',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Clínica Patitas')
            ->assertSee('01 555 0199 · contacto@clinicapatitas.test · Av. Veterinaria 123, Lima')
            ->assertSee('https://facebook.com/clinicapatitas', false)
            ->assertSee('https://instagram.com/clinicapatitas', false)
            ->assertSee('https://www.tiktok.com/@clinicapatitas', false)
            ->assertSee('https://wa.me/51999999999', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_empty_social_settings_hide_public_social_navigation(): void
    {
        ClinicSetting::query()->firstOrFail()->update([
            'instagram_url' => null,
            'facebook_url' => null,
            'tiktok_url' => null,
            'whatsapp_number' => null,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('aria-label="Redes sociales y contacto"', false)
            ->assertDontSee('aria-label="Contactar por WhatsApp"', false);
    }

    public function test_admin_can_configure_public_social_links_after_password_confirmation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $settings = ClinicSetting::query()->firstOrFail();
        $payload = [
            'name' => 'VetCare',
            'phone' => '555-0199',
            'email' => 'contacto@vetcare.test',
            'address' => 'Lima',
            'appointment_minutes' => 30,
            'instagram_url' => 'https://instagram.com/vetcare',
            'facebook_url' => 'https://facebook.com/vetcare',
            'tiktok_url' => 'https://tiktok.com/@vetcare',
            'whatsapp_number' => '51999999999',
            'current_password' => 'password',
            '_version' => RecordVersion::of($settings),
            '_preview' => '1',
        ];

        $preview = $this->actingAs($admin)->patchJson('/admin/settings', $payload)->assertOk()->json();
        $this->assertDatabaseMissing('clinic_settings', ['instagram_url' => 'https://instagram.com/vetcare']);

        $this->patchJson('/admin/settings', [
            ...$payload,
            '_preview' => '0',
            'confirmation_token' => $preview['confirmation_token'],
        ])->assertOk();

        $this->assertDatabaseHas('clinic_settings', [
            'instagram_url' => 'https://instagram.com/vetcare',
            'facebook_url' => 'https://facebook.com/vetcare',
            'tiktok_url' => 'https://tiktok.com/@vetcare',
            'whatsapp_number' => '51999999999',
        ]);
    }

    public function test_social_configuration_rejects_unsafe_or_malformed_links(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $settings = ClinicSetting::query()->firstOrFail();

        $this->actingAs($admin)->patchJson('/admin/settings', [
            'name' => 'VetCare',
            'appointment_minutes' => 30,
            'instagram_url' => 'javascript:alert(1)',
            'facebook_url' => 'javascript:alert(1)',
            'tiktok_url' => 'https://example.com/not-tiktok',
            'whatsapp_number' => '+51 999 999 999',
            'current_password' => 'password',
            '_version' => RecordVersion::of($settings),
            '_preview' => '1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['facebook_url', 'instagram_url', 'tiktok_url', 'whatsapp_number']);

        $this->assertDatabaseMissing('clinic_settings', ['instagram_url' => 'javascript:alert(1)']);
    }
}
