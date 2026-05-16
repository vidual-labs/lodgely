<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-tenant AI provider configuration. Always look this up via
 * AiSetting::forTenant($id) so a sensible default row is created if
 * none exists yet — keeps Livewire pages simple.
 *
 * The API key column on disk holds Laravel-encrypted ciphertext; the
 * apiKey() accessor decrypts on demand. The UI never re-displays it.
 */
class AiSetting extends Model
{
    protected $table = 'ai_settings';

    protected $fillable = [
        'tenant_id',
        'enabled',
        'provider',
        'base_url',
        'api_key_encrypted',
        'model',
        'house_style',
        'kinds_enabled',
        'lead_data_consent',
        'temperature',
    ];

    protected function casts(): array
    {
        return [
            'enabled'           => 'boolean',
            'lead_data_consent' => 'boolean',
            'kinds_enabled'     => 'array',
            'temperature'       => 'float',
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
            [
                'enabled'           => false,
                'kinds_enabled'     => ['report_view' => false, 'lead_qualification' => false],
                'lead_data_consent' => false,
            ]
        );
    }

    /** Decrypts the stored ciphertext. Returns null if no key is set or it fails to decrypt. */
    public function apiKey(): ?string
    {
        if (! $this->api_key_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_key_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Stores an encrypted version of $plain. Pass null to clear. */
    public function setApiKey(?string $plain): void
    {
        $this->api_key_encrypted = ($plain === null || $plain === '')
            ? null
            : Crypt::encryptString($plain);
    }

    public function isKindEnabled(string $kindValue): bool
    {
        $kinds = $this->kinds_enabled ?? [];

        return (bool) ($kinds[$kindValue] ?? false);
    }

    /** Effective base URL: stored override, or the config default for the provider. */
    public function effectiveBaseUrl(): ?string
    {
        if ($this->base_url) {
            return rtrim((string) $this->base_url, '/');
        }
        $default = config("lodgely.ai.defaults.{$this->provider}.base_url");

        return $default ? rtrim((string) $default, '/') : null;
    }

    /** Effective model name: stored override, or the config default for the provider. */
    public function effectiveModel(): ?string
    {
        return $this->model
            ?: config("lodgely.ai.defaults.{$this->provider}.model");
    }
}
