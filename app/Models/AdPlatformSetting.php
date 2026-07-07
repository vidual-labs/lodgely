<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Meta Ads + Google Ads API credentials, one row per tenant *connector*.
 * `client_name === null` is the original single "default" connector (shared
 * across the tenant); a row with `client_name` set is a connector assigned
 * to one client, so an operator can run separate Meta/Google ad accounts per
 * client. Always resolve the default via forTenant() (creates it) for
 * UI/admin contexts, or resolveSafe() (read-only, never writes, survives a
 * missing table) from the import adapters.
 *
 * Secret columns hold Laravel-encrypted ciphertext and are never
 * re-displayed. The effective* getters resolve the live value: the stored
 * UI value when present, otherwise the env/config fallback — so installs
 * that still configure credentials in .env keep working unchanged.
 */
class AdPlatformSetting extends Model
{
    protected $table = 'ad_platform_settings';

    protected $fillable = [
        'tenant_id',
        'client_name',
        'meta_enabled',
        'meta_ad_account_id',
        'meta_currency',
        'meta_api_version',
        'meta_access_token_encrypted',
        'google_enabled',
        'google_customer_id',
        'google_login_customer_id',
        'google_api_version',
        'google_client_id',
        'google_client_secret_encrypted',
        'google_refresh_token_encrypted',
        'google_developer_token_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'meta_enabled'   => 'boolean',
            'google_enabled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function forTenant(int $tenantId): self
    {
        return self::firstOrCreate(['tenant_id' => $tenantId, 'client_name' => null]);
    }

    /** Fetch (or create) the connector assigned to a specific client. */
    public static function forClient(int $tenantId, string $clientName): self
    {
        return self::firstOrCreate(['tenant_id' => $tenantId, 'client_name' => $clientName]);
    }

    /**
     * Read-only resolution for the import adapters. Never writes a row and
     * never throws if the table doesn't exist yet (e.g. unit tests that skip
     * migrations) — callers fall back to env config via the effective* getters.
     */
    public static function resolveSafe(int $tenantId, ?string $clientName = null): self
    {
        try {
            return self::query()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($clientName) {
                    $clientName === null ? $q->whereNull('client_name') : $q->where('client_name', $clientName);
                })
                ->first() ?? new self();
        } catch (\Throwable) {
            return new self();
        }
    }

    /**
     * Every connector row configured for a tenant — the default (client_name
     * null) first, then per-client ones alphabetically. Used to render the
     * connectors list and to resolve which credentials a fetch should run
     * against.
     *
     * @return Collection<int, self>
     */
    public static function connectorsForTenant(int $tenantId): Collection
    {
        try {
            return self::query()
                ->where('tenant_id', $tenantId)
                ->orderByRaw('client_name IS NOT NULL')
                ->orderBy('client_name')
                ->get();
        } catch (\Throwable) {
            return new Collection();
        }
    }

    /**
     * Connectors with the given platform toggled on, for the live fetch
     * pipeline. Falls back to a single synthetic (unsaved) connector when
     * nothing is toggled on in the UI, so pure env-var installs keep working
     * exactly as before multi-connector support existed.
     *
     * @return list<self>
     */
    public static function activeConnectorsForPlatform(int $tenantId, string $platform): array
    {
        $column = $platform.'_enabled';

        try {
            $rows = self::query()->where('tenant_id', $tenantId)->where($column, true)->get();
        } catch (\Throwable) {
            $rows = new Collection();
        }

        return $rows->isEmpty() ? [new self()] : $rows->all();
    }

    private static function decrypt(?string $cipher): ?string
    {
        if (! $cipher) {
            return null;
        }

        try {
            return Crypt::decryptString($cipher);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function encrypt(?string $plain): ?string
    {
        return ($plain === null || $plain === '') ? null : Crypt::encryptString($plain);
    }

    // --- Meta secrets ---

    public function metaAccessToken(): ?string
    {
        return self::decrypt($this->meta_access_token_encrypted);
    }

    public function setMetaAccessToken(?string $plain): void
    {
        $this->meta_access_token_encrypted = self::encrypt($plain);
    }

    // --- Google secrets ---

    public function googleClientSecret(): ?string
    {
        return self::decrypt($this->google_client_secret_encrypted);
    }

    public function setGoogleClientSecret(?string $plain): void
    {
        $this->google_client_secret_encrypted = self::encrypt($plain);
    }

    public function googleRefreshToken(): ?string
    {
        return self::decrypt($this->google_refresh_token_encrypted);
    }

    public function setGoogleRefreshToken(?string $plain): void
    {
        $this->google_refresh_token_encrypted = self::encrypt($plain);
    }

    public function googleDeveloperToken(): ?string
    {
        return self::decrypt($this->google_developer_token_encrypted);
    }

    public function setGoogleDeveloperToken(?string $plain): void
    {
        $this->google_developer_token_encrypted = self::encrypt($plain);
    }

    // --- Effective values: stored UI value, else env/config fallback ---

    public function effectiveMetaAccessToken(): string
    {
        return $this->metaAccessToken() ?: (string) config('lodgely.reporting.meta.access_token', '');
    }

    public function effectiveMetaAccountId(): string
    {
        return (string) ($this->meta_ad_account_id ?: config('lodgely.reporting.meta.ad_account_id', ''));
    }

    public function effectiveMetaApiVersion(): string
    {
        return (string) ($this->meta_api_version ?: config('lodgely.reporting.meta.api_version', 'v21.0'));
    }

    public function effectiveMetaCurrency(): string
    {
        return (string) ($this->meta_currency ?: config('lodgely.reporting.meta.currency', 'USD'));
    }

    public function effectiveGoogleClientId(): string
    {
        return (string) ($this->google_client_id ?: config('lodgely.reporting.google.client_id', ''));
    }

    public function effectiveGoogleClientSecret(): string
    {
        return $this->googleClientSecret() ?: (string) config('lodgely.reporting.google.client_secret', '');
    }

    public function effectiveGoogleRefreshToken(): string
    {
        return $this->googleRefreshToken() ?: (string) config('lodgely.reporting.google.refresh_token', '');
    }

    public function effectiveGoogleDeveloperToken(): string
    {
        return $this->googleDeveloperToken() ?: (string) config('lodgely.reporting.google.developer_token', '');
    }

    public function effectiveGoogleCustomerId(): string
    {
        return (string) ($this->google_customer_id ?: config('lodgely.reporting.google.customer_id', ''));
    }

    public function effectiveGoogleLoginCustomerId(): string
    {
        return (string) ($this->google_login_customer_id ?: config('lodgely.reporting.google.login_customer_id', ''));
    }

    public function effectiveGoogleApiVersion(): string
    {
        return (string) ($this->google_api_version ?: config('lodgely.reporting.google.api_version', 'v18'));
    }

    // --- Connection state (UI badges) ---

    public function isMetaConnected(): bool
    {
        return $this->effectiveMetaAccessToken() !== '' && $this->effectiveMetaAccountId() !== '';
    }

    public function hasGoogleCredentials(): bool
    {
        return $this->effectiveGoogleClientId() !== '' && $this->effectiveGoogleClientSecret() !== '';
    }

    public function isGoogleConnected(): bool
    {
        return $this->hasGoogleCredentials()
            && $this->effectiveGoogleRefreshToken() !== ''
            && $this->effectiveGoogleDeveloperToken() !== ''
            && $this->effectiveGoogleCustomerId() !== '';
    }

    /**
     * Which ad-metrics source keys should run for this tenant. The env list
     * (LODGELY_AD_METRICS_SOURCES, default the mocks) is the base; the UI
     * toggles switch on the live `meta` / `google` adapters, so an operator can
     * go live without touching .env, and env-only installs keep working.
     *
     * The demo `*_mock` adapters are suppressed the moment any real platform is
     * connected through the UI: once an operator has live data, the
     * deterministic demo campaigns must not pollute their reporting. Fresh /
     * demo installs that haven't connected anything keep the mocks.
     *
     * @return string[]
     */
    public static function activeSourceKeys(int $tenantId): array
    {
        $keys = array_values(array_filter(array_map(
            'trim',
            (array) config('lodgely.reporting.sources', []),
        )));

        try {
            $metaEnabled = self::query()->where('tenant_id', $tenantId)->where('meta_enabled', true)->exists();
            $googleEnabled = self::query()->where('tenant_id', $tenantId)->where('google_enabled', true)->exists();
        } catch (\Throwable) {
            $metaEnabled = false;
            $googleEnabled = false;
        }

        $liveConnected = false;
        if ($metaEnabled) {
            $keys[] = 'meta';
            $liveConnected = true;
        }
        if ($googleEnabled) {
            $keys[] = 'google';
            $liveConnected = true;
        }

        // Real connection present → drop the demo mock sources so only live
        // data is ingested. The `_mock` suffix is the established convention
        // for demo adapters (meta_mock, google_mock).
        if ($liveConnected) {
            $keys = array_filter($keys, static fn (string $key): bool => ! str_ends_with($key, '_mock'));
        }

        return array_values(array_unique($keys));
    }
}
