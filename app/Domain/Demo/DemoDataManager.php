<?php

namespace App\Domain\Demo;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\DuplicateDetector;
use App\Models\Import;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLeadScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Loads and unloads the canonical demo dataset (the same content the
 * DatabaseSeeder ships) at runtime, so operators can populate a fresh
 * install or wipe demo content from the inbox without dropping the DB.
 *
 * Demo content is marked by attaching every demo lead to a dedicated
 * Import row (source = self::IMPORT_SOURCE). This makes unload a single
 * scoped delete instead of guessing which leads are "real" vs. seeded.
 */
class DemoDataManager
{
    /** Import.source value used to tag the demo lead batch. */
    public const IMPORT_SOURCE = 'demo_seed';

    /**
     * The well-known demo client accounts the seeder creates. Their
     * scopes and leads are removed on unload. The demo operator
     * (operator@example.com) is preserved so the user clicking
     * "unload" doesn't lock themselves out.
     */
    public const DEMO_CLIENT_EMAILS = [
        'client.northwind@example.com',
        'client.acme@example.com',
    ];

    public const DEMO_OPERATOR_EMAIL = 'operator@example.com';

    public function __construct(private readonly DuplicateDetector $detector) {}

    /**
     * @return array{loaded: bool, demo_leads: int, demo_users: int, has_import: bool}
     */
    public function status(): array
    {
        $leadCount = Lead::query()
            ->whereIn('import_id', $this->demoImportIds())
            ->count();

        $userCount = User::query()
            ->whereIn('email', self::DEMO_CLIENT_EMAILS)
            ->count();

        $hasImport = Import::query()
            ->where('tenant_id', Tenant::DEFAULT_ID)
            ->where('source', self::IMPORT_SOURCE)
            ->exists();

        return [
            'loaded'     => $leadCount > 0 || $userCount > 0 || $hasImport,
            'demo_leads' => $leadCount,
            'demo_users' => $userCount,
            'has_import' => $hasImport,
        ];
    }

    /**
     * Seed the demo dataset. Safe to call repeatedly: it short-circuits
     * if a demo import already exists.
     *
     * @return array{created_leads: int, created_users: int, already_loaded: bool}
     */
    public function load(): array
    {
        if ($this->status()['loaded']) {
            return ['created_leads' => 0, 'created_users' => 0, 'already_loaded' => true];
        }

        return DB::transaction(function (): array {
            Tenant::firstOrCreate(
                ['id' => Tenant::DEFAULT_ID],
                ['slug' => 'default', 'name' => config('lodgely.brand.name')],
            );

            $createdUsers = 0;

            $operator = User::where('email', self::DEMO_OPERATOR_EMAIL)->first();
            if (! $operator) {
                User::create([
                    'name'      => 'Demo Operator',
                    'email'     => self::DEMO_OPERATOR_EMAIL,
                    'password'  => Hash::make('password'),
                    'role'      => 'operator',
                    'is_active' => true,
                ]);
                $createdUsers++;
            }

            $clientA = $this->ensureClientUser(
                'client.northwind@example.com',
                'Northwind Studio Owner',
                'Northwind Studio',
                $createdUsers,
            );

            $clientB = $this->ensureClientUser(
                'client.acme@example.com',
                'Acme Wellness Owner',
                'Acme Wellness',
                $createdUsers,
            );

            $import = Import::create([
                'tenant_id' => Tenant::DEFAULT_ID,
                'source'    => self::IMPORT_SOURCE,
                'label'     => 'Demo dataset · '.now()->format('Y-m-d H:i'),
                'meta'      => ['loaded_at' => now()->toIso8601String()],
            ]);

            Lead::factory()->count(60)->create(['import_id' => $import->id]);

            foreach (['Northwind Studio', 'Acme Wellness'] as $clientName) {
                Lead::factory()->count(6)->meta()->create([
                    'client_name' => $clientName,
                    'import_id'   => $import->id,
                ]);
            }

            Lead::query()
                ->where('import_id', $import->id)
                ->whereNotNull('custom_answers')
                ->update(['custom_answers' => null]);

            Lead::query()
                ->where('import_id', $import->id)
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

            $primary = Lead::factory()->create([
                'import_id'        => $import->id,
                'full_name'        => 'Jordan Bennett',
                'email'            => 'jordan.bennett@example.com',
                'email_normalized' => 'jordan.bennett@example.com',
                'phone'            => '+49 30 1112233',
                'phone_normalized' => '49301112233',
                'client_name'      => 'Northwind Studio',
                'source'           => 'csv',
                'status'           => LeadStatus::New->value,
            ]);
            Lead::factory()->create([
                'import_id'        => $import->id,
                'full_name'        => 'Jordan Bennett',
                'email'            => 'JORDAN.bennett+test@example.com',
                'email_normalized' => 'jordan.bennett@example.com',
                'phone'            => '+49 30 1112233',
                'phone_normalized' => '49301112233',
                'client_name'      => 'Northwind Studio',
                'source'           => 'email_mock',
                'status'           => LeadStatus::New->value,
            ]);

            foreach (Lead::query()->where('import_id', $import->id)->orderBy('id')->cursor() as $lead) {
                $this->detector->reconcile($lead);
            }

            $createdLeads = Lead::query()->where('import_id', $import->id)->count();

            $import->update([
                'rows_total'    => $createdLeads,
                'rows_imported' => $createdLeads,
                'started_at'    => $import->created_at,
                'finished_at'   => now(),
            ]);

            return [
                'created_leads'  => $createdLeads,
                'created_users'  => $createdUsers,
                'already_loaded' => false,
            ];
        });
    }

    /**
     * Tear down the demo dataset. Removes demo leads (force-deleted so
     * they don't sit in the soft-delete trash), demo client users, and
     * the tracking Import row.
     *
     * The currently authenticated user is never deleted, so an operator
     * who signed in as a demo account can still safely click "unload".
     *
     * @return array{deleted_leads: int, deleted_users: int}
     */
    public function unload(): array
    {
        return DB::transaction(function (): array {
            $importIds = $this->demoImportIds();

            $deletedLeads = 0;
            if ($importIds !== []) {
                Lead::withTrashed()
                    ->whereIn('import_id', $importIds)
                    ->update(['duplicate_of_id' => null]);

                $deletedLeads = Lead::withTrashed()
                    ->whereIn('import_id', $importIds)
                    ->forceDelete();

                Import::query()->whereIn('id', $importIds)->delete();
            }

            $currentUserId = auth()->id();

            $clientUsers = User::query()
                ->whereIn('email', self::DEMO_CLIENT_EMAILS)
                ->when($currentUserId, fn ($q) => $q->where('id', '!=', $currentUserId))
                ->get();

            $deletedUsers = 0;
            foreach ($clientUsers as $user) {
                UserLeadScope::where('user_id', $user->id)->delete();
                $user->delete();
                $deletedUsers++;
            }

            return [
                'deleted_leads' => $deletedLeads,
                'deleted_users' => $deletedUsers,
            ];
        });
    }

    private function ensureClientUser(string $email, string $name, string $clientName, int &$createdCounter): User
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name'      => $name,
                'email'     => $email,
                'password'  => Hash::make('password'),
                'role'      => 'client',
                'is_active' => true,
            ]);
            $createdCounter++;
        }

        UserLeadScope::firstOrCreate([
            'user_id'     => $user->id,
            'client_name' => $clientName,
        ]);

        return $user;
    }

    /** @return array<int, int> */
    private function demoImportIds(): array
    {
        return Import::query()
            ->where('tenant_id', Tenant::DEFAULT_ID)
            ->where('source', self::IMPORT_SOURCE)
            ->pluck('id')
            ->all();
    }
}
