<?php

namespace App\Models;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'import_id', 'source',
        'client_name', 'campaign_name',
        'full_name', 'email', 'phone',
        'email_normalized', 'phone_normalized',
        'message', 'raw_payload', 'custom_answers',
        'status', 'priority',
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

        return $query->whereIn(
            \DB::raw('LOWER(client_name)'),
            array_map(static fn ($v) => mb_strtolower((string) $v), $allowed)
        );
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereRaw('LOWER(full_name) LIKE ?', [$like])
              ->orWhereRaw('LOWER(email) LIKE ?', [$like])
              ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
              ->orWhereRaw('LOWER(message) LIKE ?', [$like])
              ->orWhereRaw('LOWER(client_name) LIKE ?', [$like])
              ->orWhereRaw('LOWER(campaign_name) LIKE ?', [$like]);
        });
    }
}
