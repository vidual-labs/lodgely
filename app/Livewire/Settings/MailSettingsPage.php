<?php

namespace App\Livewire\Settings;

use App\Mail\TestMailMessage;
use App\Models\MailSetting;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Operator-only UI for outbound mail (SMTP). Lets an operator point lodgely at
 * their mail server without editing .env, so reporting emails and password
 * resets actually leave the box. The password is write-only in the form
 * (blank = "leave as-is") and stored encrypted on the MailSetting row.
 *
 * When "Use these settings" is off the page is inert and lodgely falls back to
 * the .env / config mail settings, so env-only installs keep working.
 */
#[Layout('components.layouts.app')]
class MailSettingsPage extends Component
{
    /** @var array<string, mixed> */
    public array $form = [
        'enabled'        => false,
        'mailer'         => 'smtp',
        'host'           => '',
        'port'           => null,
        'encryption'     => 'tls',
        'username'       => '',
        'password'       => '',     // write-only; blank means "leave as-is"
        'has_password'   => false,
        'from_address'   => '',
        'from_name'      => '',
        'test_recipient' => '',
    ];

    public ?string $testResult = null;

    public function mount(): void
    {
        $this->guardOperator();
        $this->loadFromDb();
        $this->form['test_recipient'] = (string) auth()->user()?->email;
    }

    private function loadFromDb(): void
    {
        $row = MailSetting::forTenant(Tenant::DEFAULT_ID);

        $this->form = array_merge($this->form, [
            'enabled'      => (bool) $row->enabled,
            'mailer'       => (string) ($row->mailer ?: 'smtp'),
            'host'         => (string) $row->host,
            'port'         => $row->port,
            'encryption'   => (string) ($row->encryption ?: 'tls'),
            'username'     => (string) $row->username,
            'password'     => '',
            'has_password' => $row->hasPassword(),
            'from_address' => (string) $row->from_address,
            'from_name'    => (string) $row->from_name,
        ]);
    }

    public function save(): void
    {
        $this->guardOperator();

        $data = $this->validate($this->rules())['form'];

        $row = MailSetting::forTenant(Tenant::DEFAULT_ID);
        $row->enabled      = (bool) $data['enabled'];
        $row->mailer       = $data['mailer'];
        $row->host         = trim((string) $data['host']) ?: null;
        $row->port         = $data['port'] !== null && $data['port'] !== '' ? (int) $data['port'] : null;
        $row->encryption   = $data['encryption'];
        $row->username     = trim((string) $data['username']) ?: null;
        $row->from_address = trim((string) $data['from_address']) ?: null;
        $row->from_name    = trim((string) $data['from_name']) ?: null;

        if (! empty($data['password'])) {
            $row->setPassword($data['password']);
        }

        $row->save();

        $this->loadFromDb();
        $this->dispatch('toast', message: __('Mail settings saved.'), type: 'success');
    }

    public function clearPassword(): void
    {
        $this->guardOperator();

        $row = MailSetting::forTenant(Tenant::DEFAULT_ID);
        $row->setPassword(null);
        $row->save();

        $this->loadFromDb();
        $this->dispatch('toast', message: __('SMTP password cleared.'), type: 'success');
    }

    /**
     * Sends a real test email against the *saved* settings (save first), run
     * synchronously so SMTP failures — bad auth, refused connection, blocked
     * port — surface straight back to the operator instead of vanishing into a
     * queued job's failure log.
     */
    public function sendTest(): void
    {
        $this->guardOperator();

        $this->validateOnly('form.test_recipient', [
            'form.test_recipient' => ['required', 'email'],
        ]);

        $row = MailSetting::forTenant(Tenant::DEFAULT_ID);

        if (! $row->isSmtpConfigured()) {
            $this->testResult = 'error:'.__('Turn on "Use these settings", choose SMTP, fill in the host and Save before sending a test.');
            return;
        }

        try {
            // Apply right now so the probe uses the saved row even if this
            // request booted before the settings existed.
            $row->applyToConfig();

            Mail::to($this->form['test_recipient'])
                ->send(new TestMailMessage((string) config('lodgely.brand.name', 'lodgely')));

            $this->testResult = 'success:'.__('Test email sent to :email. Check the inbox (and the spam folder).', [
                'email' => $this->form['test_recipient'],
            ]);
        } catch (\Throwable $e) {
            $this->testResult = 'error:'.$e->getMessage();
        }
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'form.enabled'        => ['boolean'],
            'form.mailer'         => ['required', Rule::in(['smtp', 'log'])],
            'form.host'           => ['nullable', 'string', 'max:255', Rule::requiredIf(
                fn () => (bool) $this->form['enabled'] && $this->form['mailer'] === 'smtp',
            )],
            'form.port'           => ['nullable', 'integer', 'min:1', 'max:65535'],
            'form.encryption'     => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'form.username'       => ['nullable', 'string', 'max:255'],
            'form.password'       => ['nullable', 'string', 'max:500'],
            'form.from_address'   => ['nullable', 'email', 'max:255'],
            'form.from_name'      => ['nullable', 'string', 'max:255'],
            'form.test_recipient' => ['nullable', 'email'],
        ];
    }

    public function render(): View
    {
        return view('livewire.settings.mail-settings-page');
    }

    private function guardOperator(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }
}
