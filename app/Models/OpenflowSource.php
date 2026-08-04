<?php

namespace App\Models;

use App\Models\Concerns\HasRecurringFetchSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * A single OpenFlow form configured as a recurring lead source.
 *
 * OpenFlow (the self-hosted form builder) exposes no API token — its only
 * auth is a JWT minted from an email/password login. So we store the login
 * email plus an encrypted password and mint a token on each pull. See
 * {@see \App\Importers\Openflow\OpenflowClient}.
 *
 * field_map: {openflow_field_id: lead_field_key}
 *   e.g. {"a1b2c3": "full_name", "x9y8z7": "email"}
 * Unmapped fields with an answer are surfaced as custom answers, using the
 * OpenFlow field label as the question — so nothing in a submission is lost.
 *
 * Always resolve via OpenflowSource::forTenant() so tenant isolation is never
 * accidentally bypassed.
 */
class OpenflowSource extends Model
{
    use HasRecurringFetchSchedule;

    protected $table = 'openflow_sources';

    protected $fillable = [
        'tenant_id',
        'label',
        'base_url',
        'email',
        'password_encrypted',
        'api_token_encrypted',
        'form_id',
        'form_name',
        'field_map',
        'default_client_name',
        'default_campaign_name',
        'refresh_hours',
        'last_fetched_at',
        'last_successful_fetch_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'field_map'       => 'array',
            'refresh_hours'   => 'integer',
            'last_fetched_at' => 'datetime',
            'last_successful_fetch_at' => 'datetime',
            'is_active'       => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Base URL with any trailing slash trimmed, ready to have `/api/...`
     * appended. Never returns a trailing slash.
     */
    public function normalizedBaseUrl(): string
    {
        return rtrim(trim((string) $this->base_url), '/');
    }

    /** Decrypt the stored login password, or null if absent/corrupt. */
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

    /** Encrypt and store a login password. Passing null/'' clears it. */
    public function setPassword(?string $plain): void
    {
        $this->password_encrypted = ($plain === null || $plain === '')
            ? null
            : Crypt::encryptString($plain);
    }

    /** Decrypt the stored read-only API token, or null if absent/corrupt. */
    public function apiToken(): ?string
    {
        if (! $this->api_token_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_token_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Encrypt and store an API token. Passing null/'' clears it. */
    public function setApiToken(?string $plain): void
    {
        $this->api_token_encrypted = ($plain === null || $plain === '')
            ? null
            : Crypt::encryptString(trim($plain));
    }

    /** True when this source authenticates with an API token rather than a login. */
    public function usesToken(): bool
    {
        return $this->api_token_encrypted !== null;
    }

    public function hasCredentials(): bool
    {
        if (trim((string) $this->base_url) === '') {
            return false;
        }

        // Either a token, or an email + password login.
        return $this->api_token_encrypted !== null
            || (trim((string) $this->email) !== '' && $this->password_encrypted !== null);
    }

    /**
     * Mappable lead field keys and their display labels, shown in the field
     * mapping UI. Unmapped OpenFlow fields fall through to custom answers, so
     * this stays focused on the columns that drive lead behaviour.
     */
    public static function leadFields(): array
    {
        return [
            // Core contact
            'full_name'     => 'Full name',
            'email'         => 'Email',
            'phone'         => 'Phone',
            'message'       => 'Message',
            // Assignment
            'client_name'   => 'Client name',
            'campaign_name' => 'Campaign name',
            // Status & priority
            'status'        => 'Status (new/reviewed/incomplete/forwarded)',
            'priority'      => 'Priority (low/medium/high)',
            // Named custom answer — operator supplies the key
            'custom_answer' => 'Custom answer (named key)…',
        ];
    }
}
