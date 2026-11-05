<?php

namespace Tests\Feature\Inbox;

use App\Livewire\Inbox\InboxPage;
use App\Models\SavedFilter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Native HTML form endpoints for the "Save current view" panel and the
 * Saved-views chips (load / ★ default / delete).
 *
 * Like every inbox write-panel (CLAUDE.md, "Inbox filter-card forms"), they
 * must send the operator back to the inbox they came from. Three ways they
 * did not:
 *  - the forms post the search term as `search`, but the redirect read `q`,
 *    so saving a view dropped the search from the inbox;
 *  - `sort` only accepted 3 of the inbox's sorts, so a view saved while
 *    sorted by a column header (name, status, …) failed validation, with no
 *    message shown in the panel;
 *  - the ★ / delete chips carried no filter state, so starring or deleting a
 *    view reset every active filter.
 */
class InboxSavedFilterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function operator(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name' => 'Op', 'email' => 'op@example.com', 'password' => Hash::make('p'),
            'role' => 'operator', 'is_active' => true,
        ]);
    }

    /** @return array<string, string> */
    private function redirectQuery($response): array
    {
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $params);
        ksort($params);

        return $params;
    }

    /** @return array<string, string> the hidden inputs the panel forms post */
    private function state(array $overrides = []): array
    {
        return $overrides + [
            'search' => 'jordan', 'status' => 'new', 'priority' => '', 'source' => '',
            'client' => 'Acme', 'outreach' => '', 'sort' => 'name_asc',
        ];
    }

    public function test_saving_a_view_returns_to_the_same_inbox_including_the_search(): void
    {
        $op = $this->operator();

        $response = $this->actingAs($op)->post('/inbox/saved-filters', ['name' => 'Jordan'] + $this->state());

        $response->assertSessionHasNoErrors();
        $this->assertSame(
            ['client' => 'Acme', 'q' => 'jordan', 'sort' => 'name_asc', 'status' => 'new'],
            $this->redirectQuery($response),
        );
    }

    public function test_a_view_can_be_saved_with_any_inbox_sort(): void
    {
        $op = $this->operator();

        foreach (['name_asc', 'status_desc', 'priority_asc', 'client_desc', 'created_asc'] as $sort) {
            $this->actingAs($op)
                ->post('/inbox/saved-filters', ['name' => $sort] + $this->state(['sort' => $sort]))
                ->assertSessionHasNoErrors();

            $this->assertSame($sort, SavedFilter::where('name', $sort)->sole()->filters['sort']);
        }

        $this->actingAs($op)
            ->post('/inbox/saved-filters', ['name' => 'bad'] + $this->state(['sort' => 'DROP TABLE']))
            ->assertSessionHasErrors('sort');
    }

    public function test_starring_or_deleting_a_view_keeps_the_active_filters(): void
    {
        $op = $this->operator();
        $view = SavedFilter::create([
            'user_id' => $op->id, 'tenant_id' => Tenant::DEFAULT_ID, 'name' => 'Mine',
            'filters' => ['status' => 'new'], 'is_default' => false,
        ]);

        foreach (['default', 'delete'] as $action) {
            $response = $this->actingAs($op)->post("/inbox/saved-filters/{$view->id}", ['action' => $action] + $this->state());

            $this->assertSame(
                ['client' => 'Acme', 'q' => 'jordan', 'sort' => 'name_asc', 'status' => 'new'],
                $this->redirectQuery($response),
                $action,
            );
        }
    }

    public function test_the_saved_view_chips_post_the_current_filter_state(): void
    {
        $op = $this->operator();
        SavedFilter::create([
            'user_id' => $op->id, 'tenant_id' => Tenant::DEFAULT_ID, 'name' => 'Mine',
            'filters' => ['status' => 'new'], 'is_default' => false,
        ]);

        $html = Livewire::actingAs($op)->test(InboxPage::class)
            ->set('search', 'jordan')
            ->set('client', 'Acme')
            ->html();

        $this->assertSame(1, preg_match('#<form method="POST" action="[^"]*/inbox/saved-filters/\d+".*?</form>#s', $html, $m));
        $this->assertMatchesRegularExpression('#name="search"\s+value="jordan"#', $m[0]);
        $this->assertMatchesRegularExpression('#name="client"\s+value="Acme"#', $m[0]);
    }
}
