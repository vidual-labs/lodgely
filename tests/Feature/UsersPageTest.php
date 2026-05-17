<?php

namespace Tests\Feature;

use App\Livewire\Users\UsersPage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UsersPageTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name'      => 'Test Operator',
            'email'     => 'op@example.com',
            'password'  => Hash::make('password'),
            'role'      => 'operator',
            'is_active' => true,
        ]);
    }

    public function test_clients_cannot_open_the_users_page(): void
    {
        $client = User::create([
            'name' => 'Client', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $this->actingAs($client)
            ->get('/users')
            ->assertForbidden();
    }

    public function test_operator_can_create_a_scoped_client(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)
            ->test(UsersPage::class)
            ->call('openCreate')
            ->set('form.name', 'New Client')
            ->set('form.email', 'new.client@example.com')
            ->set('form.role', 'client')
            ->set('form.password', 'supersecret1')
            ->set('form.is_active', true)
            ->set('form.scopes_input', 'Brand A, Brand B')
            ->call('save')
            ->assertHasNoErrors();

        $u = User::where('email', 'new.client@example.com')->first();
        $this->assertNotNull($u);
        $this->assertSame('client', $u->role->value);
        $this->assertEqualsCanonicalizing(
            ['Brand A', 'Brand B'],
            $u->leadScopes->pluck('client_name')->all()
        );
    }

    public function test_operator_cannot_demote_themselves(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)
            ->test(UsersPage::class)
            ->call('openEdit', $op->id)
            ->set('form.role', 'client')
            ->call('save')
            ->assertHasErrors('form.role');

        $this->assertSame('operator', $op->fresh()->role->value);
    }

    public function test_operator_cannot_deactivate_themselves_via_toggle(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)
            ->test(UsersPage::class)
            ->call('toggleActive', $op->id);

        $this->assertTrue((bool) $op->fresh()->is_active);
    }

    public function test_operator_can_send_a_reset_link_to_an_active_user(): void
    {
        Notification::fake();
        $op    = $this->operator();
        $other = User::create([
            'name' => 'Other', 'email' => 'other@example.com', 'password' => Hash::make('x'),
            'role' => 'operator', 'is_active' => true,
        ]);

        Livewire::actingAs($op)
            ->test(UsersPage::class)
            ->call('sendResetLink', $other->id);

        Notification::assertSentTo($other, ResetPassword::class);
    }

    public function test_inactive_users_do_not_receive_reset_links(): void
    {
        Notification::fake();
        $op       = $this->operator();
        $disabled = User::create([
            'name' => 'Off', 'email' => 'off@example.com', 'password' => Hash::make('x'),
            'role' => 'client', 'is_active' => false,
        ]);

        Livewire::actingAs($op)
            ->test(UsersPage::class)
            ->call('sendResetLink', $disabled->id);

        Notification::assertNothingSentTo($disabled);
    }
}
