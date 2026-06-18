<?php

namespace Tests\Feature;

use App\Livewire\Reporting\ReportingPage;
use App\Models\AdSpendReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ReportingPageRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);
    }

    private function operator(): User
    {
        return User::create([
            'name' => 'Op',
            'email' => 'ops@example.com',
            'password' => Hash::make('p'),
            'role' => 'operator',
            'is_active' => true,
        ]);
    }

    private function seedSpend(string $currency = 'EUR'): void
    {
        AdSpendReport::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'platform' => 'meta',
            'date' => now()->subDay()->toDateString(),
            'campaign_id' => 'C1',
            'campaign_name' => 'Spring Push',
            'impressions' => 1000,
            'clicks' => 50,
            'spend_cents' => 123456,
            'currency' => $currency,
            'reach' => 800,
            'platform_leads' => 5,
        ]);
    }

    /**
     * Regression: the trend-chart closure referenced $kpis without capturing it,
     * 500-ing the whole page once any ad spend existed (has_data === true).
     */
    public function test_reporting_page_renders_with_data_in_account_currency(): void
    {
        $this->seedSpend('EUR');

        Livewire::actingAs($this->operator())
            ->test(ReportingPage::class)
            ->assertOk()
            ->assertSee('€1,234.56'); // 123456 cents of spend, EUR symbol
    }

    public function test_reporting_page_renders_with_no_data(): void
    {
        Livewire::actingAs($this->operator())
            ->test(ReportingPage::class)
            ->assertOk();
    }
}
