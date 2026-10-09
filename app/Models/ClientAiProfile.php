<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-client "ideal customer" text the AI ranker reads on top of the
 * operator-wide ranking profile. Keyed by the free-text client_name, which
 * is also how leads and client users are scoped — so every lookup here is
 * case-insensitive, matching {@see Lead::scopeForClientName()}.
 *
 * Client users edit their own scopes on /profile; operators edit every
 * client on /settings/ai.
 */
class ClientAiProfile extends Model
{
    protected $table = 'client_ai_profiles';

    protected $fillable = ['tenant_id', 'client_name', 'profile', 'updated_by'];

    public const MAX_LENGTH = 2000;

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function findFor(int $tenantId, string $clientName): ?self
    {
        $needle = mb_strtolower(trim($clientName));
        if ($needle === '') {
            return null;
        }

        return self::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(client_name) = ?', [$needle])
            ->first();
    }

    /** The profile text for a lead's client_name, or null when none is set. */
    public static function textFor(int $tenantId, ?string $clientName): ?string
    {
        if ($clientName === null || trim($clientName) === '') {
            return null;
        }

        $text = trim((string) (self::findFor($tenantId, $clientName)?->profile ?? ''));

        return $text === '' ? null : $text;
    }

    /**
     * Create or update the profile for a client name. A blank profile deletes
     * the row so the list of "clients with a profile" stays honest. Returns
     * the saved row, or null when it was cleared.
     */
    public static function put(int $tenantId, string $clientName, ?string $profile, ?int $userId = null): ?self
    {
        $clientName = trim($clientName);
        if ($clientName === '') {
            return null;
        }

        $existing = self::findFor($tenantId, $clientName);
        $profile  = trim((string) $profile);

        if ($profile === '') {
            $existing?->delete();

            return null;
        }

        $row = $existing ?? new self(['tenant_id' => $tenantId, 'client_name' => $clientName]);
        $row->profile    = $profile;
        $row->updated_by = $userId;
        $row->save();

        return $row;
    }
}
