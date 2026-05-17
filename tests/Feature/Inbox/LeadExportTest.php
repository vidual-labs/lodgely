<?php

namespace Tests\Feature\Inbox;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Http\Controllers\LeadExportController;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLeadScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LeadExportTest extends TestCase
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

    private function clientFor(string $clientName): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $client = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);

        UserLeadScope::create([
            'user_id' => $client->id,
            'tenant_id' => Tenant::DEFAULT_ID,
            'client_name' => $clientName,
        ]);

        return $client;
    }

    private function streamBody($response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/inbox/export?format=csv')->assertRedirect('/login');
    }

    public function test_client_is_forbidden(): void
    {
        $client = $this->clientFor('Acme');

        $this->actingAs($client)
            ->get('/inbox/export?format=csv')
            ->assertForbidden();
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->actingAs($this->operator())
            ->get('/inbox/export?format=xml')
            ->assertStatus(422);
    }

    public function test_operator_csv_export_contains_all_leads_with_expected_columns(): void
    {
        $op = $this->operator();
        Lead::factory()->count(3)->create();

        $response = $this->actingAs($op)->get('/inbox/export?format=csv');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $this->streamBody($response->baseResponse);
        $lines = array_values(array_filter(preg_split("/\r\n|\n/", $body)));

        $this->assertCount(4, $lines, 'expected header + 3 rows');
        $header = str_getcsv($lines[0]);
        $this->assertSame(LeadExportController::COLUMNS, $header);

        $this->assertStringNotContainsString('raw_payload', $body);
        $this->assertStringNotContainsString('email_normalized', $body);
        $this->assertStringNotContainsString('phone_normalized', $body);
    }

    public function test_ndjson_export_emits_one_object_per_line(): void
    {
        $op = $this->operator();
        Lead::factory()->count(2)->create();

        $response = $this->actingAs($op)->get('/inbox/export?format=ndjson');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-ndjson');

        $body = $this->streamBody($response->baseResponse);
        $lines = array_values(array_filter(explode("\n", $body)));
        $this->assertCount(2, $lines);

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            $this->assertIsArray($decoded);
            $this->assertSame(LeadExportController::COLUMNS, array_keys($decoded));
        }
    }

    public function test_status_filter_is_honoured(): void
    {
        $op = $this->operator();
        Lead::factory()->create(['status' => LeadStatus::New->value, 'full_name' => 'Nora New']);
        Lead::factory()->create(['status' => LeadStatus::Reviewed->value, 'full_name' => 'Roger Reviewed']);
        Lead::factory()->create(['status' => LeadStatus::Forwarded->value, 'full_name' => 'Fiona Forwarded']);

        $response = $this->actingAs($op)->get('/inbox/export?format=csv&status=new');
        $body = $this->streamBody($response->baseResponse);

        $this->assertStringContainsString('Nora New', $body);
        $this->assertStringNotContainsString('Roger Reviewed', $body);
        $this->assertStringNotContainsString('Fiona Forwarded', $body);
    }

    public function test_search_filter_is_honoured(): void
    {
        $op = $this->operator();
        Lead::factory()->create(['client_name' => 'Acme Wellness', 'full_name' => 'Alice A']);
        Lead::factory()->create(['client_name' => 'Northwind Studio', 'full_name' => 'Bob B']);

        $response = $this->actingAs($op)->get('/inbox/export?format=csv&q=acme');
        $body = $this->streamBody($response->baseResponse);

        $this->assertStringContainsString('Alice A', $body);
        $this->assertStringNotContainsString('Bob B', $body);
    }

    public function test_priority_filter_is_honoured(): void
    {
        $op = $this->operator();
        Lead::factory()->create(['priority' => LeadPriority::High->value, 'full_name' => 'High Pri']);
        Lead::factory()->create(['priority' => LeadPriority::Low->value, 'full_name' => 'Low Pri']);

        $response = $this->actingAs($op)->get('/inbox/export?format=csv&priority=high');
        $body = $this->streamBody($response->baseResponse);

        $this->assertStringContainsString('High Pri', $body);
        $this->assertStringNotContainsString('Low Pri', $body);
    }

    public function test_export_is_logged(): void
    {
        $op = $this->operator();
        Lead::factory()->count(2)->create();

        Log::spy();

        $response = $this->actingAs($op)->get('/inbox/export?format=csv&status=new');
        $this->streamBody($response->baseResponse);

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($op) {
                return $message === 'lead.exported'
                    && $context['user_id'] === $op->id
                    && $context['format'] === 'csv'
                    && is_int($context['count'])
                    && ($context['filters']['status'] ?? null) === 'new';
            });
    }
}
