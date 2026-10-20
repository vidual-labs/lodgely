<?php

namespace App\Domain\Leads\Services;

use App\Models\Lead;
use App\Models\LeadEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The lead-metadata change feed for vigilo (LG-15, contract v1 — vigilo
 * `docs/LODGELY-CONTRACT.md`).
 *
 * Privacy is structural, not a filter: {@see self::toContract()} builds every
 * item from an explicit list of non-personal columns, so a new PII column on
 * `leads` can never leak into the feed by accident. No name, email, phone,
 * message, answers, raw payload, notes, AI output or click id is ever read
 * here.
 *
 * The feed runs over *changes*: leads ordered by (updated_at, id), including
 * soft-deleted ones, which go out once as tombstones. Every write path on a
 * lead bumps `updated_at` (Eloquent saves, soft deletes, the duplicate
 * detector's raw update), which is what makes a status change on an old lead
 * reach vigilo.
 */
class LeadMetadataFeed
{
    public const CONTRACT_VERSION = 1;

    public const MAX_LIMIT = 500;

    /**
     * lodgely's internal source keys → the contract's `source_type`. The
     * contract speaks of the kind of source, so both email importers are
     * `email` and both Meta keys (live importer, demo data) are `meta_lead_ads`.
     */
    private const SOURCE_TYPES = [
        'csv'           => 'csv',
        'email_mock'    => 'email',
        'email_imap'    => 'email',
        'manual'        => 'manual',
        'webhook'       => 'webhook',
        'google_sheets' => 'google_sheets',
        'meta_leads'    => 'meta_lead_ads',
        'meta_ads'      => 'meta_lead_ads',
        'openflow'      => 'openflow',
    ];

    /**
     * The client names a contract client key stands for. Until the Client
     * model (LG-4) exists, the key is `Str::slug(client_name)`; names that
     * differ only in case or punctuation share a key, and all of them belong
     * to it — the same leniency as {@see Lead::scopeForClientNames()}.
     * Soft-deleted leads count, so a client whose leads were all purged still
     * resolves and its tombstones still go out.
     *
     * @return list<string>
     */
    public function clientNamesForKey(int $tenantId, string $key): array
    {
        if ($key === '') {
            return [];
        }

        return Lead::withTrashed()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('client_name')
            ->distinct()
            ->pluck('client_name')
            ->filter(static fn ($name) => Str::slug((string) $name) === $key)
            ->values()
            ->all();
    }

    /**
     * One page of the feed.
     *
     * @param  list<string>  $clientNames
     * @param  array{u: string, i: int}|null  $after  position from a decoded cursor
     * @return array{data: list<array<string, mixed>>, next_cursor: ?string, has_more: bool}
     */
    public function page(int $tenantId, array $clientNames, ?array $after, ?CarbonInterface $updatedSince, int $limit, ?string $givenCursor = null): array
    {
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $query = Lead::withTrashed()
            ->where('tenant_id', $tenantId)
            ->forClientNames($clientNames)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit + 1)
            ->select([
                'id', 'source', 'status', 'created_at', 'updated_at', 'deleted_at',
                'campaign_id', 'adset_id', 'ad_id', 'form_id',
                'duplicate_flag', 'qualified_at', 'called_at', 'mailed_at', 'is_organic',
            ]);

        if ($after !== null) {
            $query->where(static function ($q) use ($after) {
                $q->where('updated_at', '>', $after['u'])
                    ->orWhere(static fn ($q) => $q->where('updated_at', $after['u'])->where('id', '>', $after['i']));
            });
        } elseif ($updatedSince !== null) {
            $query->where('updated_at', '>=', self::dbTimestamp($updatedSince));
        }

        $leads = $query->get();
        $hasMore = $leads->count() > $limit;
        $leads = $leads->take($limit)->values();

        $statusChangedAt = $this->statusChangedAt($leads);
        $data = $leads->map(fn (Lead $lead) => $this->toContract($lead, $statusChangedAt[$lead->id] ?? null))->all();

        $last = $leads->last();
        $nextCursor = $last !== null
            ? self::encodeCursor(self::dbTimestamp($last->updated_at), (int) $last->id)
            : $givenCursor;

        return ['data' => $data, 'next_cursor' => $nextCursor, 'has_more' => $hasMore];
    }

    /**
     * One lead as a contract item: a tombstone for a deleted lead, otherwise
     * metadata from an explicit allow-list of columns.
     *
     * @return array<string, mixed>
     */
    public function toContract(Lead $lead, ?CarbonInterface $statusChangedAt = null): array
    {
        if ($lead->deleted_at !== null) {
            return [
                'id'         => self::contractId($lead),
                'deleted'    => true,
                'updated_at' => self::iso($lead->updated_at),
            ];
        }

        return [
            'id'                => self::contractId($lead),
            'submitted_at'      => self::iso($lead->created_at), // LG-3 will add the real submission time
            'updated_at'        => self::iso($lead->updated_at),
            'status'            => $lead->status?->value,
            'status_changed_at' => $statusChangedAt ? self::iso($statusChangedAt) : null,
            'source_type'       => self::sourceType($lead->source),
            'attribution'       => [
                'meta_campaign_id' => self::nullableString($lead->campaign_id),
                'meta_adset_id'    => self::nullableString($lead->adset_id),
                'meta_ad_id'       => self::nullableString($lead->ad_id),
                'meta_form_id'     => self::nullableString($lead->form_id),
                // No per-lead click-id attribution yet (LG-6); vigilo falls
                // back to platform conversions for Google until then.
                'has_gclid'        => false,
                'utm_source'       => null,
                'utm_medium'       => null,
                'utm_campaign'     => null,
            ],
            'flags'             => [
                'duplicate' => (bool) $lead->duplicate_flag,
                'qualified' => $lead->qualified_at !== null,
                'contacted' => $lead->called_at !== null || $lead->mailed_at !== null,
                'organic'   => $lead->is_organic === true,
            ],
        ];
    }

    public static function contractId(Lead $lead): string
    {
        return 'ld_'.$lead->id;
    }

    public static function sourceType(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        $type = self::SOURCE_TYPES[$source] ?? $source;

        return preg_match('/^[a-z0-9_]{1,40}$/', $type) === 1 ? $type : null;
    }

    public static function encodeCursor(string $updatedAt, int $id): string
    {
        return rtrim(strtr(base64_encode((string) json_encode(['u' => $updatedAt, 'i' => $id])), '+/', '-_'), '=');
    }

    /** @return array{u: string, i: int}|null null when the cursor is not one of ours */
    public static function decodeCursor(string $cursor): ?array
    {
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);

        if (! is_array($data) || ! is_string($data['u'] ?? null) || ! is_int($data['i'] ?? null)) {
            return null;
        }

        // The position is compared with the stored column, so it must be a
        // timestamp in exactly the stored format and nothing else.
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['u']) !== 1) {
            return null;
        }

        return ['u' => $data['u'], 'i' => $data['i']];
    }

    /**
     * When each lead's status last changed, from the audit trail.
     *
     * @param  Collection<int, Lead>  $leads
     * @return array<int, Carbon>
     */
    private function statusChangedAt(Collection $leads): array
    {
        $ids = $leads->whereNull('deleted_at')->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        return LeadEvent::query()
            ->whereIn('lead_id', $ids)
            ->where('type', 'lead.status_changed')
            ->groupBy('lead_id')
            ->selectRaw('lead_id, MAX(created_at) AS changed_at')
            ->toBase()
            ->pluck('changed_at', 'lead_id')
            ->map(static fn ($at) => Carbon::parse($at))
            ->all();
    }

    /** A timestamp as `leads.updated_at` stores it (app timezone, seconds). */
    private static function dbTimestamp(CarbonInterface $at): string
    {
        return $at->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    private static function iso(CarbonInterface $at): string
    {
        return $at->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
