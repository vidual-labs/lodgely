<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * The refresh schedule shared by every recurring lead source
 * ({@see \App\Models\GoogleSheetSource}, {@see \App\Models\MetaLeadSource},
 * {@see \App\Models\OpenflowSource}).
 *
 * Each carries `is_active`, `refresh_hours` and `last_fetched_at`, and each
 * had a byte-identical isDue(). The hourly scheduler asks every source the
 * same question — "has your interval elapsed?" — so it should get its answer
 * from one place.
 *
 * Note `last_fetched_at` is the *scheduling* clock and advances on failed
 * attempts too, deliberately, so a broken source isn't retried every hour. A
 * source whose adapter also needs a data high-water mark must keep that on a
 * separate column (see OpenflowSource::last_successful_fetch_at).
 */
trait HasRecurringFetchSchedule
{
    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->last_fetched_at === null) {
            return true;
        }

        return $this->last_fetched_at->addHours($this->refresh_hours)->isPast();
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
