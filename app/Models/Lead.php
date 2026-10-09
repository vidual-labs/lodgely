<?php

namespace App\Models;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Enums\PrioritySource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'import_id', 'source', 'external_id',
        'client_name', 'campaign_name',
        'full_name', 'email', 'phone',
        'email_normalized', 'phone_normalized',
        'message', 'raw_payload', 'custom_answers',
        'status', 'priority',
        'priority_source', 'ai_priority', 'ai_reason', 'ai_tags', 'ai_ranked_at',
        'duplicate_flag', 'duplicate_of_id',
        'retention_until',
        'qualified_at', 'called_at', 'mailed_at',
        'meta_lead_id',
        'ad_id', 'ad_name',
        'adset_id', 'adset_name',
        'campaign_id',
        'form_id', 'form_name',
        'platform', 'is_organic',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload'     => 'array',
            'custom_answers'  => 'array',
            'duplicate_flag'  => 'boolean',
            'is_organic'      => 'boolean',
            'retention_until' => 'datetime',
            'qualified_at'    => 'datetime',
            'called_at'       => 'datetime',
            'mailed_at'       => 'datetime',
            'status'          => LeadStatus::class,
            'priority'        => LeadPriority::class,
            'priority_source' => PrioritySource::class,
            'ai_priority'     => LeadPriority::class,
            'ai_tags'         => 'array',
            'ai_ranked_at'    => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function events(): HasMany
    {
        return $this->hasMany(LeadEvent::class)->latest();
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    /** True while the current priority is the one the AI ranker wrote (no human override since). */
    public function isAiRanked(): bool
    {
        return $this->priority_source === PrioritySource::Ai;
    }

    /** @return list<string> */
    public function aiTags(): array
    {
        return array_values(array_filter(
            array_map(static fn ($t) => trim((string) $t), (array) ($this->ai_tags ?? [])),
            static fn (string $t) => $t !== '',
        ));
    }

    // ------------------------------------------------------------------ scopes

    /**
     * Restrict to leads a given user is allowed to see.
     *  - Operators (or null user, used in CLI): no restriction.
     *  - Clients: only leads whose client_name matches one of their scopes.
     *    Match is case-insensitive on client_name to avoid silly drift.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null || $user->isOperator()) {
            return $query;
        }

        $allowed = $user->allowedClientNames() ?? [];

        if ($allowed === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->forClientNames($allowed);
    }

    /**
     * Match a single client_name case-insensitively. client_name is free text
     * typed by operators and echoed by importers, so it drifts in case ("Acme"
     * vs "acme") and every comparison in the app has to be case-insensitive —
     * this is the one place that decides how.
     */
    public function scopeForClientName(Builder $query, ?string $clientName): Builder
    {
        return $query->forClientNames($clientName === null ? [] : [$clientName]);
    }

    /**
     * Match any of several client_names case-insensitively. An empty list
     * matches nothing (not everything) — see scopeVisibleTo(), where a client
     * with no assigned scopes must see zero leads.
     *
     * @param  string[]  $clientNames
     */
    public function scopeForClientNames(Builder $query, array $clientNames): Builder
    {
        if ($clientNames === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            DB::raw('LOWER(client_name)'),
            array_map(static fn ($v) => mb_strtolower((string) $v), $clientNames),
        );
    }

    /**
     * The campaign ids that a set of clients' leads carry — the bridge used to
     * attribute the shared/default ad connector's tenant-wide spend down to one
     * client. Reporting has three separate consumers of this
     * ({@see \App\Domain\Reporting\Services\CampaignRollup},
     * {@see \App\Domain\Reporting\Services\CreativeRollup},
     * {@see \App\Domain\Reporting\Services\ClientViewDataBuilder}); they must
     * all attribute identically or a client's KPI strip stops matching their
     * own campaign table.
     *
     * @param  string[]  $clientNames
     * @return list<string>
     */
    public static function campaignIdsForClients(int $tenantId, array $clientNames): array
    {
        if ($clientNames === []) {
            return [];
        }

        return static::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('campaign_id')
            ->forClientNames($clientNames)
            ->distinct()
            ->pluck('campaign_id')
            ->all();
    }

    /**
     * The Outreach inbox filter — matches the qualified/called/mailed columns
     * shown as pills on the Outreach table/panel section. `not_contacted`
     * means all three are still unset. Unrecognized values are a no-op rather
     * than an error, matching {@see LeadIngestor::coerceStatus()}'s
     * never-throw-on-bad-input convention for anything ultimately sourced
     * from a URL query param.
     */
    public function scopeOutreachStatus(Builder $query, string $value): Builder
    {
        return match ($value) {
            'qualified' => $query->whereNotNull('qualified_at'),
            'called' => $query->whereNotNull('called_at'),
            'mailed' => $query->whereNotNull('mailed_at'),
            'not_contacted' => $query->whereNull('qualified_at')->whereNull('called_at')->whereNull('mailed_at'),
            default => $query,
        };
    }

    /**
     * Leads the hourly AI ranker still has to look at. This is the single
     * definition of "unranked": never ranked, no human-set priority (a person's
     * choice is never overwritten), not a duplicate, and no ranking attempt in
     * the last 24 h — the ai_summaries row is created at dispatch time, so a
     * lead the model keeps failing on is retried at most once a day and the
     * hourly run and the "Rank now" button can never double-dispatch.
     */
    public function scopeRankingCandidates(Builder $query, int $tenantId): Builder
    {
        return $query
            ->where('leads.tenant_id', $tenantId)
            ->whereNull('leads.ai_ranked_at')
            ->where(function (Builder $q) {
                $q->whereNull('leads.priority_source')
                  ->orWhere('leads.priority_source', '!=', PrioritySource::User->value);
            })
            ->where('leads.duplicate_flag', false)
            ->whereNotExists(function ($sub) {
                $sub->from('ai_summaries')
                    ->selectRaw('1')
                    ->whereColumn('ai_summaries.subject_id', 'leads.id')
                    ->where('ai_summaries.subject_type', self::class)
                    ->where('ai_summaries.kind', AiSummaryKind::LeadRanking->value)
                    ->where('ai_summaries.created_at', '>=', now()->subDay());
            })
            ->orderBy('leads.created_at')
            ->orderBy('leads.id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.\App\Support\Like::escape(mb_strtolower($term)).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereRaw("LOWER(full_name) LIKE ? ESCAPE '\\'", [$like])
              ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$like])
              ->orWhereRaw("LOWER(phone) LIKE ? ESCAPE '\\'", [$like])
              ->orWhereRaw("LOWER(message) LIKE ? ESCAPE '\\'", [$like])
              ->orWhereRaw("LOWER(client_name) LIKE ? ESCAPE '\\'", [$like])
              ->orWhereRaw("LOWER(campaign_name) LIKE ? ESCAPE '\\'", [$like]);
        });
    }
}
