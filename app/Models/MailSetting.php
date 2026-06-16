<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-tenant outbound mail (SMTP) configuration. One row per tenant. Resolve
 * via forTenant() for UI/admin contexts (creates a default row), or
 * resolveSafe() (read-only, never writes, survives a missing table) from the
 * runtime config applier that boots in every process — web requests and queue
 * workers alike.
 *
 * `password_encrypted` holds Laravel-encrypted ciphertext; it is decrypted
 * only when building the live mail config and never re-displayed in the UI.
 *
 * When `enabled` is false the row is inert and lodgely uses the .env / config
 * mail settings unchanged — so installs that configure SMTP in .env keep
 * working without touching the settings page.
 */
class MailSetting extends Model
{
    protected $table = 'mail_settings';

    protected $fillable = [
        'tenant_id',
        'enabled',
        'mailer',
        'host',
        'port',
        'encryption',
        'username',
        'password_encrypted',
        'from_address',
        'from_name',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'port'    => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function forTenant(int $tenantId): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['enabled' => false, 'mailer' => 'smtp', 'encryption' => 'tls'],
        );
    }

    /**
     * Read-only resolution for the runtime config applier. Never writes a row
     * and never throws if the table doesn't exist yet (fresh install before
     * migrate, or unit tests that skip migrations) — callers just get an inert
     * disabled row and fall back to .env/config.
     */
    public static function resolveSafe(int $tenantId): self
    {
        try {
            return self::query()->firstWhere('tenant_id', $tenantId) ?? new self();
        } catch (\Throwable) {
            return new self();
        }
    }

    /** Decrypts the stored ciphertext. Returns null if unset or undecryptable. */
    public function password(): ?string
    {
        if (! $this->password_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->password_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Stores an encrypted version of $plain. Pass null/'' to clear. */
    public function setPassword(?string $plain): void
    {
        $this->password_encrypted = ($plain === null || $plain === '')
            ? null
            : Crypt::encryptString($plain);
    }

    public function hasPassword(): bool
    {
        return (bool) $this->password_encrypted;
    }

    /** True once there is enough here to actually attempt an SMTP send. */
    public function isSmtpConfigured(): bool
    {
        return $this->enabled && $this->mailer === 'smtp' && (string) $this->host !== '';
    }

    /**
     * Push the stored settings into the live `mail.*` config so every mailer
     * resolved this process (web request or queue worker) uses them. No-op when
     * disabled, so .env/config stays authoritative. Called from
     * AppServiceProvider::boot() and before each queued job.
     */
    public function applyToConfig(): void
    {
        if (! $this->enabled) {
            return;
        }

        $mailer = in_array($this->mailer, ['smtp', 'log'], true) ? $this->mailer : 'smtp';
        config(['mail.default' => $mailer]);

        if ((string) $this->from_address !== '') {
            config(['mail.from.address' => $this->from_address]);
        }
        if ((string) $this->from_name !== '') {
            config(['mail.from.name' => $this->from_name]);
        }

        if ($mailer !== 'smtp') {
            return;
        }

        // ssl → implicit TLS (port 465); otherwise plain smtp scheme, on which
        // Symfony negotiates STARTTLS automatically when the server advertises
        // it (the common port-587 "tls" case).
        $scheme = $this->encryption === 'ssl' ? 'smtps' : 'smtp';
        $port   = $this->port ?: ($this->encryption === 'ssl' ? 465 : 587);

        config([
            // A MAIL_URL in env would otherwise win over the discrete fields.
            'mail.mailers.smtp.url'      => null,
            'mail.mailers.smtp.host'     => $this->host,
            'mail.mailers.smtp.port'     => (int) $port,
            'mail.mailers.smtp.username' => $this->username ?: null,
            'mail.mailers.smtp.password' => $this->password() ?: null,
            'mail.mailers.smtp.scheme'   => $scheme,
        ]);
    }

    /** Convenience for the service-provider boot + queue hooks. */
    public static function applyForDefaultTenant(): void
    {
        self::resolveSafe(Tenant::DEFAULT_ID)->applyToConfig();
    }
}
