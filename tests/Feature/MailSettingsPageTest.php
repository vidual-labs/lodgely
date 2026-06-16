<?php

namespace Tests\Feature;

use App\Livewire\Settings\MailSettingsPage;
use App\Mail\TestMailMessage;
use App\Models\MailSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class MailSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return User::create([
            'name'      => 'Op',
            'email'     => 'op@example.com',
            'password'  => Hash::make('p'),
            'role'      => 'operator',
            'is_active' => true,
        ]);
    }

    public function test_client_cannot_open_mail_settings(): void
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        $client = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('p'),
            'role' => 'client', 'is_active' => true,
        ]);

        $this->actingAs($client)->get('/settings/mail')->assertForbidden();
    }

    public function test_operator_saves_settings_and_password_is_encrypted_at_rest(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)
            ->test(MailSettingsPage::class)
            ->set('form.enabled', true)
            ->set('form.mailer', 'smtp')
            ->set('form.host', 'smtp.example.com')
            ->set('form.port', 587)
            ->set('form.encryption', 'tls')
            ->set('form.username', 'leads@example.com')
            ->set('form.password', 's3cret-pw')
            ->set('form.from_address', 'no-reply@example.com')
            ->set('form.from_name', 'Example')
            ->call('save')
            ->assertHasNoErrors();

        $row = MailSetting::forTenant(Tenant::DEFAULT_ID);

        $this->assertTrue($row->enabled);
        $this->assertSame('smtp.example.com', $row->host);
        $this->assertSame(587, $row->port);
        $this->assertSame('no-reply@example.com', $row->from_address);
        $this->assertNotNull($row->password_encrypted);
        $this->assertNotSame('s3cret-pw', $row->password_encrypted, 'Password must be encrypted at rest');
        $this->assertSame('s3cret-pw', Crypt::decryptString($row->password_encrypted));
    }

    public function test_blank_password_does_not_clear_the_stored_one(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)->test(MailSettingsPage::class)
            ->set('form.enabled', true)
            ->set('form.host', 'smtp.example.com')
            ->set('form.password', 'first-pw')
            ->call('save');

        Livewire::actingAs($op)->test(MailSettingsPage::class)
            ->set('form.host', 'smtp2.example.com')
            ->set('form.password', '')
            ->call('save');

        $row = MailSetting::forTenant(Tenant::DEFAULT_ID);
        $this->assertSame('smtp2.example.com', $row->host);
        $this->assertSame('first-pw', $row->password());
    }

    public function test_host_is_required_when_smtp_enabled(): void
    {
        $op = $this->operator();

        Livewire::actingAs($op)->test(MailSettingsPage::class)
            ->set('form.enabled', true)
            ->set('form.mailer', 'smtp')
            ->set('form.host', '')
            ->call('save')
            ->assertHasErrors('form.host');
    }

    public function test_apply_to_config_overrides_runtime_mail_config(): void
    {
        $this->operator();

        $row = MailSetting::forTenant(Tenant::DEFAULT_ID);
        $row->enabled    = true;
        $row->mailer     = 'smtp';
        $row->host       = 'smtp.example.com';
        $row->port       = 465;
        $row->encryption = 'ssl';
        $row->setPassword('pw');
        $row->save();

        $row->applyToConfig();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('pw', config('mail.mailers.smtp.password'));
    }

    public function test_disabled_row_leaves_config_untouched(): void
    {
        config()->set('mail.default', 'log');

        (new MailSetting(['enabled' => false, 'mailer' => 'smtp', 'host' => 'smtp.example.com']))
            ->applyToConfig();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_send_test_dispatches_mail_when_configured(): void
    {
        Mail::fake();
        $op = $this->operator();

        $row = MailSetting::forTenant(Tenant::DEFAULT_ID);
        $row->enabled = true;
        $row->mailer  = 'smtp';
        $row->host    = 'smtp.example.com';
        $row->save();

        Livewire::actingAs($op)->test(MailSettingsPage::class)
            ->set('form.test_recipient', 'me@example.com')
            ->call('sendTest');

        Mail::assertSent(TestMailMessage::class, fn (TestMailMessage $m) => $m->hasTo('me@example.com'));
    }

    public function test_send_test_errors_when_not_configured(): void
    {
        $op = $this->operator();

        $component = Livewire::actingAs($op)->test(MailSettingsPage::class)
            ->set('form.test_recipient', 'me@example.com')
            ->call('sendTest');

        $this->assertStringStartsWith('error:', (string) $component->get('testResult'));
    }
}
