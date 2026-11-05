<?php

namespace Database\Seeders;

use App\Domain\Demo\DemoDataManager;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Bootstrap a demo install: the default tenant plus the canonical demo
     * dataset — the demo operator (`operator@example.com`), two scoped client
     * logins and ~74 neutral leads with a known duplicate pair.
     *
     * The dataset comes from {@see DemoDataManager::load()}, the same code the
     * Settings → Demo data page runs, so seeded leads carry the demo_seed
     * import tag and that page can remove them again. (The seeder used to
     * build its own untagged copy, which "Unload" could not see.) load() is a
     * no-op while demo content exists, so re-running the seeder no longer
     * piles up another 74 leads.
     */
    public function run(): void
    {
        // Default tenant — single-tenant MVP, but schema is ready for more.
        Tenant::firstOrCreate(
            ['id' => Tenant::DEFAULT_ID],
            ['slug' => 'default', 'name' => config('lodgely.brand.name')],
        );

        // The README's first sign-in. Ensured on its own because load() skips
        // everything while any demo content (e.g. mock ad metrics) exists. An
        // existing account keeps its password.
        User::firstOrCreate(
            ['email' => DemoDataManager::DEMO_OPERATOR_EMAIL],
            [
                'name'      => 'Demo Operator',
                'password'  => Hash::make('password'),
                'role'      => 'operator',
                'is_active' => true,
            ],
        );

        app(DemoDataManager::class)->load();
    }
}
