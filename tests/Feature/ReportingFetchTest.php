<?php

namespace Tests\Feature;

use App\Models\AdSpendReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportingFetchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('p'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_operator_fetch_populates_ad_metrics_from_mock_source(): void
    {
        // Keep only the deterministic mock active — no live HTTP needed.
        config()->set('lodgely.reporting.sources', ['meta_mock']);

        $op = $this->user('operator', 'ops@example.com');

        $this->assertSame(0, AdSpendReport::count());

        $this->actingAs($op)
            ->post(route('reporting.ad-metrics.fetch'))
            ->assertRedirect(route('reporting'))
            ->assertSessionHas('status');

        // 3 mock campaigns × 7 days (today inclusive) = 21 distinct rows.
        $this->assertSame(21, AdSpendReport::where('platform', 'meta')->count());
    }

    public function test_fetch_reports_when_no_sources_connected(): void
    {
        // A configured key that matches no registered adapter resolves to zero
        // sources (no live Meta/Google toggled on either).
        config()->set('lodgely.reporting.sources', ['none']);

        $op = $this->user('operator', 'ops@example.com');

        $this->actingAs($op)
            ->post(route('reporting.ad-metrics.fetch'))
            ->assertRedirect(route('reporting'))
            ->assertSessionHas('status');

        $this->assertSame(0, AdSpendReport::count());
    }

    public function test_client_cannot_fetch_ad_metrics(): void
    {
        config()->set('lodgely.reporting.sources', ['meta_mock']);

        $client = $this->user('client', 'c@example.com');

        $this->actingAs($client)
            ->post(route('reporting.ad-metrics.fetch'))
            ->assertForbidden();

        $this->assertSame(0, AdSpendReport::count());
    }
}
