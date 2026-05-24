<?php

namespace Tests\Feature;

use App\Domain\Demo\DemoDataManager;
use App\Livewire\Settings\DemoDataPage;
use App\Models\Import;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DemoDataPageTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::firstOrCreate(
            ['id' => Tenant::DEFAULT_ID],
            ['slug' => 'default', 'name' => 'lodgely'],
        );
    }

    private function operator(string $email = 'ops@example.com'): User
    {
        $this->tenant();

        return User::create([
            'name'      => 'Op',
            'email'     => $email,
            'password'  => Hash::make('p'),
            'role'      => 'operator',
            'is_active' => true,
        ]);
    }

    private function client(string $email = 'c@example.com'): User
    {
        $this->tenant();

        return User::create([
            'name'      => 'Client',
            'email'     => $email,
            'password'  => Hash::make('p'),
            'role'      => 'client',
            'is_active' => true,
        ]);
    }

    public function test_client_cannot_access_page(): void
    {
        $this->actingAs($this->client())->get('/settings/demo-data')->assertForbidden();
    }

    public function test_operator_can_load_and_unload_demo_data(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)
            ->test(DemoDataPage::class)
            ->assertSet('status.loaded', false)
            ->assertSet('status.demo_leads', 0)
            ->call('load')
            ->assertSet('status.loaded', true);

        $this->assertGreaterThan(0, Lead::count());
        $this->assertSame(1, Import::where('source', DemoDataManager::IMPORT_SOURCE)->count());
        $this->assertNotNull(User::where('email', 'client.northwind@example.com')->first());
        $this->assertNotNull(User::where('email', 'client.acme@example.com')->first());

        Livewire::actingAs($op)
            ->test(DemoDataPage::class)
            ->call('unload')
            ->assertSet('status.loaded', false)
            ->assertSet('status.demo_leads', 0);

        $this->assertSame(0, Lead::count());
        $this->assertSame(0, Import::where('source', DemoDataManager::IMPORT_SOURCE)->count());
        $this->assertNull(User::where('email', 'client.northwind@example.com')->first());
        $this->assertNull(User::where('email', 'client.acme@example.com')->first());
    }

    public function test_load_is_idempotent_when_data_already_present(): void
    {
        $op = $this->operator();

        app(DemoDataManager::class)->load();
        $afterFirstLeadCount = Lead::count();

        $result = app(DemoDataManager::class)->load();

        $this->assertTrue($result['already_loaded']);
        $this->assertSame($afterFirstLeadCount, Lead::count());
    }

    public function test_unload_preserves_real_imports_and_leads(): void
    {
        $op = $this->operator();

        $realImport = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'csv',
            'label'     => 'real customer upload',
        ]);
        $realLead = Lead::factory()->create(['import_id' => $realImport->id]);

        app(DemoDataManager::class)->load();
        app(DemoDataManager::class)->unload();

        $this->assertTrue(Import::whereKey($realImport->id)->exists());
        $this->assertTrue(Lead::whereKey($realLead->id)->exists());
    }

    public function test_unload_does_not_delete_currently_signed_in_user(): void
    {
        $this->tenant();

        $signedInDemoClient = User::create([
            'name'      => 'Demo client signed in',
            'email'     => 'client.northwind@example.com',
            'password'  => Hash::make('p'),
            'role'      => 'client',
            'is_active' => true,
        ]);

        $this->actingAs($signedInDemoClient);

        app(DemoDataManager::class)->unload();

        $this->assertNotNull(User::find($signedInDemoClient->id));
    }
}
