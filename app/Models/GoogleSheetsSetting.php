<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-tenant Google Sheets OAuth credentials. One row per tenant.
 * Always resolve via GoogleSheetsSetting::forTenant() so a default
 * row is upserted transparently — keeps callers simple.
 *
 * client_secret and refresh_token are stored as Laravel-encrypted
 * ciphertext; never re-expose them in API responses or audit payloads.
 */
class GoogleSheetsSetting extends Model
{
    protected $table = 'google_sheets_settings';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'client_secret_encrypted',
        'refresh_token_encrypted',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function forTenant(int $tenantId): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['client_id' => ''],
        );
    }

    public function clientSecret(): ?string
    {
        if (! $this->client_secret_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->client_secret_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setClientSecret(?string $plain): void
    {
        $this->client_secret_encrypted = ($plain === null || $plain === '')
            ? null
            : Crypt::encryptString($plain);
    }

    public function refreshToken(): ?string
    {
        if (! $this->refresh_token_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->refresh_token_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setRefreshToken(?string $plain): void
    {
        $this->refresh_token_encrypted = ($plain === null || $plain === '')
            ? null
            : Crypt::encryptString($plain);
    }

    public function isConnected(): bool
    {
        return $this->client_id !== ''
            && $this->client_secret_encrypted !== null
            && $this->refresh_token_encrypted !== null;
    }

    public function hasCredentials(): bool
    {
        return $this->client_id !== '' && $this->client_secret_encrypted !== null;
    }
}
