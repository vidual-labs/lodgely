<?php

namespace Database\Seeders;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\DuplicateDetector;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLeadScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default tenant — single-tenant MVP, but schema is ready for more.
        Tenant::firstOrCreate(
            ['id' => Tenant::DEFAULT_ID],
            ['slug' => 'default', 'name' => config('lodgely.brand.name')],
        );

        // Operator account (e.g. the agency / inhouse marketing lead).
        $operator = User::updateOrCreate(
            ['email' => 'operator@example.com'],
            [
                'name'      => 'Demo Operator',
                'password'  => Hash::make('password'),
                'role'      => 'operator',
                'is_active' => true,
            ],
        );

        // Two scoped client users — see only "their" leads.
        $clientA = User::updateOrCreate(
            ['email' => 'client.northwind@example.com'],
            [
                'name'      => 'Northwind Studio Owner',
                'password'  => Hash::make('password'),
                'role'      => 'client',
                'is_active' => true,
            ],
        );
        UserLeadScope::firstOrCreate(['user_id' => $clientA->id, 'client_name' => 'Northwind Studio']);

        $clientB = User::updateOrCreate(
            ['email' => 'client.acme@example.com'],
            [
                'name'      => 'Acme Wellness Owner',
                'password'  => Hash::make('password'),
                'role'      => 'client',
                'is_active' => true,
            ],
        );
        UserLeadScope::firstOrCreate(['user_id' => $clientB->id, 'client_name' => 'Acme Wellness']);

        // Neutral demo leads spread across sources, statuses and clients.
        Lead::factory()->count(60)->create();

        // Meta Lead Ads sample set — six per demo client so both client
        // logins land on a populated, varied Meta inbox with ad/adset/form
        // attribution and outreach state. Seeded leads do NOT carry mock
        // custom-question answers — real Meta ingestion populates those.
        foreach (['Northwind Studio', 'Acme Wellness'] as $clientName) {
            Lead::factory()->count(6)->meta()->create(['client_name' => $clientName]);
        }

        // Defensive: clear any stale custom_answers left in the DB from
        // earlier seed runs that used the now-removed mock CUSTOM_QUESTIONS
        // pool — so the Columns dropdown doesn't surface "What is your
        // budget?" / "Preferred contact method?" etc on existing dev DBs.
        Lead::query()->whereNotNull('custom_answers')->update(['custom_answers' => null]);

        // Mark a couple of Meta leads as already qualified / called / mailed
        // so the client view shows the outreach toggles in their "on" state
        // immediately after seeding.
        Lead::query()
            ->where('source', 'meta_ads')
            ->orderBy('id')
            ->limit(4)
            ->get()
            ->each(function (Lead $l, int $i): void {
                $l->qualified_at = now()->subDays($i + 1);
                if ($i % 2 === 0) {
                    $l->called_at = now()->subDays($i)->subHours(2);
                }
                if ($i % 3 === 0) {
                    $l->mailed_at = now()->subDays($i)->subHours(1);
                }
                $l->save();
            });

        // Two clear duplicates so the UI feature is visible out of the box.
        $primary = Lead::factory()->create([
            'full_name' => 'Jordan Bennett',
            'email'     => 'jordan.bennett@example.com',
            'email_normalized' => 'jordan.bennett@example.com',
            'phone'     => '+49 30 1112233',
            'phone_normalized' => '49301112233',
            'client_name' => 'Northwind Studio',
            'source' => 'csv',
            'status' => LeadStatus::New->value,
        ]);
        Lead::factory()->create([
            'full_name' => 'Jordan Bennett',
            'email'     => 'JORDAN.bennett+test@example.com',
            'email_normalized' => 'jordan.bennett@example.com',
            'phone'     => '+49 30 1112233',
            'phone_normalized' => '49301112233',
            'client_name' => 'Northwind Studio',
            'source' => 'email_mock',
            'status' => LeadStatus::New->value,
        ]);

        // Reconcile so the duplicate_of_id chain is populated for the demo set.
        $detector = app(DuplicateDetector::class);
        foreach (Lead::query()->orderBy('id')->cursor() as $lead) {
            $detector->reconcile($lead);
        }
    }
}
