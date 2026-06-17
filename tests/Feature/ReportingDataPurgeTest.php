<?php

namespace Tests\Feature;

use App\Domain\Demo\DemoDataManager;
use App\Models\AdSpendReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportingDataPurgeTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::firstOrCreate(
            ['id' => Tenant::DEFAULT_ID],
            ['slug' => 'default', 'name' => 'lodgely'],
        );
    }

    private function user(string $role, string $email): User
    {
        $this->tenant();

        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('p'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function seedAdMetrics(int $count = 3): void
    {
        for ($i = 0; $i < $count; $i++) {
            AdSpendReport::create([
                'tenant_id' => Tenant::DEFAULT_ID,
                'platform' => 'meta',
                'date' => now()->subDays($i)->toDateString(),
                'campaign_id' => 'c'.$i,
                'campaign_name' => 'Campaign '.$i,
                'impressions' => 1000,
                'clicks' => 50,
                'spend_cents' => 12345,
                'currency' => 'USD',
                'reach' => 800,
                'platform_leads' => 5,
            ]);
        }
    }

    public function test_operator_can_purge_ad_metrics(): void
    {
        $op = $this->user('operator', 'ops@example.com');
        $this->seedAdMetrics(3);

        $this->assertSame(3, AdSpendReport::count());

        $this->actingAs($op)
            ->post(route('reporting.ad-metrics.purge'))
            ->assertRedirect(route('reporting'))
            ->assertSessionHas('status');

        $this->assertSame(0, AdSpendReport::count());
    }

    public function test_client_cannot_purge_ad_metrics(): void
    {
        $client = $this->user('client', 'c@example.com');
        $this->seedAdMetrics(2);

        $this->actingAs($client)
            ->post(route('reporting.ad-metrics.purge'))
            ->assertForbidden();

        $this->assertSame(2, AdSpendReport::count());
    }

    public function test_demo_unload_clears_mock_ad_metrics_when_no_live_platform(): void
    {
        $this->user('operator', 'ops@example.com');
        $this->seedAdMetrics(4);

        $status = app(DemoDataManager::class)->status();
        $this->assertTrue($status['ad_metrics_removable']);
        $this->assertSame(4, $status['ad_metrics']);

        $result = app(DemoDataManager::class)->unload();

        $this->assertSame(4, $result['deleted_ad_metrics']);
        $this->assertSame(0, AdSpendReport::count());
    }
}
