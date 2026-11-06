<?php

namespace Tests\Feature;

use App\Domain\Demo\DemoDataManager;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `migrate --seed` is the README's bootstrap for a demo install, and the
 * Settings → Demo data page is how that demo content is removed again. The
 * seeder used to create its leads without the demo_seed import tag, so the
 * page reported "0 demo leads" and Unload left every seeded lead in the
 * inbox, indistinguishable from real ones.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_demo_leads_can_be_removed_from_the_demo_data_page(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = app(DemoDataManager::class);
        $seeded = Lead::count();

        $this->assertGreaterThan(0, $seeded);
        $this->assertSame($seeded, $manager->status()['demo_leads'], 'every seeded lead is tagged as demo content');
        $this->assertTrue(User::where('email', 'operator@example.com')->where('role', 'operator')->exists());
        $this->assertTrue(User::where('email', 'client.northwind@example.com')->exists());

        $this->actingAs(User::where('email', 'operator@example.com')->sole());
        $manager->unload();

        $this->assertSame(0, Lead::withTrashed()->count());
    }

    public function test_running_the_seeder_twice_does_not_duplicate_the_demo_leads(): void
    {
        $this->seed(DatabaseSeeder::class);
        $once = Lead::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($once, Lead::count());
    }
}
