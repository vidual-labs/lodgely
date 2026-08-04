<?php

namespace App\Models;

use App\Models\Concerns\HasRecurringFetchSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single Meta (Facebook/Instagram) Lead Ads connection configured as a
 * recurring lead source. Pulls leads directly from the Graph API using the
 * Meta credentials stored on {@see AdPlatformSetting} — no Google Sheets
 * round-trip required.
 *
 * Either a page_id (pull every active lead gen form on the page) or a form_id
 * (pull one specific form) identifies what to fetch. Always resolve via the
 * forTenant() scope so tenant isolation is never accidentally bypassed.
 */
class MetaLeadSource extends Model
{
    use HasRecurringFetchSchedule;

    protected $table = 'meta_lead_sources';

    protected $fillable = [
        'tenant_id',
        'label',
        'page_id',
        'form_id',
        'form_name',
        'default_client_name',
        'default_campaign_name',
        'lookback_days',
        'refresh_hours',
        'last_fetched_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'lookback_days'   => 'integer',
            'refresh_hours'   => 'integer',
            'last_fetched_at' => 'datetime',
            'is_active'       => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

}
