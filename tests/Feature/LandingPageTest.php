<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name'      => 'Pat',
            'email'     => 'pat@example.com',
            'password'  => Hash::make('password'),
            'role'      => 'operator',
            'is_active' => true,
        ]);
    }

    public function test_guests_see_the_landing_page_with_sign_in_and_vidual_links(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('One inbox.')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="https://vidual.org"', false)
            ->assertSee('LODGELY_LANDING_ENABLED=false', false);
    }

    public function test_landing_page_loads_no_third_party_assets(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Links out are fine; anything the browser would *fetch* must be local.
        $this->assertDoesNotMatchRegularExpression('/<(script|link|img)[^>]+(src|href)="https?:\/\//i', $html);
    }

    public function test_landing_page_keeps_the_security_headers(): void
    {
        $this->get('/')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_signed_in_users_skip_the_landing_page(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/')
            ->assertRedirect(route('inbox'));
    }

    public function test_disabling_the_landing_page_restores_the_inbox_redirect(): void
    {
        config(['lodgely.landing.enabled' => false]);

        $this->get('/')->assertRedirect(route('inbox'));
        $this->get(route('inbox'))->assertRedirect(route('login'));
    }
}
