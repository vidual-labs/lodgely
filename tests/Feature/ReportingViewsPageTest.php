<?php

namespace Tests\Feature;

use App\Domain\Reporting\Enums\ReportColumn;
use App\Livewire\Reporting\ReportingViewsPage;
use App\Models\ClientReportingView;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ReportingViewsPageTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    private function client(string $email = 'client@example.com'): User
    {
        return User::create([
            'name' => 'Brand A', 'email' => $email, 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);
    }

    public function test_operator_creates_a_live_view_assigned_to_a_client(): void
    {
        $op = $this->operator();
        $client = $this->client();

        Livewire::actingAs($op)
            ->test(ReportingViewsPage::class)
            ->call('openCreate')
            ->set('form.name', 'Monthly performance')
            ->set('form.columns', [ReportColumn::Spend->value, ReportColumn::Cpc->value])
            ->set('form.user_ids', [(string) $client->id])
            ->set('form.is_live', true)
            ->call('save')
            ->assertHasNoErrors();

        $view = ClientReportingView::firstWhere('name', 'Monthly performance');

        $this->assertNotNull($view);
        $this->assertTrue($view->is_live);
        $this->assertEqualsCanonicalizing(
            [ReportColumn::Spend->value, ReportColumn::Cpc->value],
            $view->columns,
        );
        $this->assertTrue($view->assignedUsers->contains($client->id));
    }

    public function test_toggle_live_hides_view_from_clients(): void
    {
        $op = $this->operator();
        $client = $this->client();

        $view = ClientReportingView::create([
            'tenant_id'  => Tenant::DEFAULT_ID,
            'name'       => 'Perf',
            'columns'    => [ReportColumn::LeadCount->value],
            'is_live'    => true,
            'created_by' => $op->id,
        ]);
        $view->assignedUsers()->sync([$client->id]);

        // Visible while live.
        $this->assertTrue(
            $client->reportingViews()->where('client_reporting_views.is_live', true)->exists()
        );

        Livewire::actingAs($op)
            ->test(ReportingViewsPage::class)
            ->call('toggleLive', $view->id);

        $this->assertFalse($view->fresh()->is_live);

        // No longer visible to the client once hidden.
        $this->assertFalse(
            $client->fresh()->reportingViews()->where('client_reporting_views.is_live', true)->exists()
        );
    }

    public function test_clients_cannot_open_the_views_page(): void
    {
        $this->operator();
        $client = $this->client();

        Livewire::actingAs($client)
            ->test(ReportingViewsPage::class)
            ->assertStatus(403);
    }
}
