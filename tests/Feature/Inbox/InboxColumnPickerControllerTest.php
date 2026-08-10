<?php

namespace Tests\Feature\Inbox;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Native HTML form endpoint for the "Custom columns" picker.
 *
 * Two bugs fixed here, both from the same root cause — the panel used to
 * reopen via a `?columns=1` query param, which nothing ever clears:
 *
 *  1. The redirect target carried no filter state, so applying a column
 *     pick silently reset every active filter.
 *  2. `?columns=1` stuck in the address bar forever (it isn't one of
 *     InboxPage's Livewire #[Url]-bound properties, so Livewire's own URL
 *     management never touches it), reopening the picker on *every*
 *     subsequent visit to that URL — reported as "the columns picker is
 *     always open now."
 */
class InboxColumnPickerControllerTest extends TestCase
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
        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        ksort($params);

        return $params;
    }

    public function test_apply_preserves_the_current_filter_state_in_the_redirect(): void
    {
        $op = $this->operator();

        $response = $this->actingAs($op)->post('/inbox/columns', [
            'action' => 'apply',
            'columns' => ['name', 'email'],
            'search' => 'acme',
            'status' => 'new',
            'sort' => 'priority_desc',
        ]);

        $response->assertRedirect();
        $expected = ['q' => 'acme', 'status' => 'new', 'sort' => 'priority_desc'];
        ksort($expected);
        $this->assertSame($expected, $this->redirectQuery($response));
    }

    public function test_apply_flashes_a_one_shot_open_panel_signal_not_a_sticky_query_param(): void
    {
        $op = $this->operator();

        $response = $this->actingAs($op)->post('/inbox/columns', ['action' => 'apply', 'columns' => ['name']]);

        $response->assertRedirect();
        $response->assertSessionHas('inbox.open-panel', 'columns');
        $this->assertArrayNotHasKey('columns', $this->redirectQuery($response));
    }

    public function test_reset_flashes_the_open_panel_signal_too(): void
    {
        $op = $this->operator();

        $response = $this->actingAs($op)->post('/inbox/columns', ['action' => 'reset']);

        $response->assertRedirect();
        $response->assertSessionHas('inbox.open-panel', 'columns');
    }

    /**
     * The end-to-end version of the reported bug: apply the picker, follow
     * the redirect (panel visibly open, one-shot flash still in the
     * session), then load /inbox completely fresh (a new request with no
     * flash) — the panel must be closed, not stuck open.
     */
    public function test_the_columns_panel_only_stays_open_for_the_one_request_right_after_apply(): void
    {
        $op = $this->operator();

        $response = $this->actingAs($op)->post('/inbox/columns', ['action' => 'apply', 'columns' => ['name']]);
        $response->assertRedirect();

        $reload = $this->actingAs($op)->get($response->headers->get('Location'));
        $reload->assertOk();
        $reload->assertSee('columnsOpen: true', false);

        $freshVisit = $this->actingAs($op)->get('/inbox');
        $freshVisit->assertOk();
        $freshVisit->assertSee('columnsOpen: false', false);
    }
}
