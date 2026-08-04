<?php

namespace App\Models;

use App\Models\Concerns\ScopesToClientConnectors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day of aggregate performance for a single ad, keyword or audience
 * segment — the fine-grained companion to {@see AdSpendReport}, which stays
 * campaign-level. Aggregate metrics only, no PII.
 */
class AdCreativeReport extends Model
{
    use ScopesToClientConnectors;

    public const DIMENSION_AD = 'ad';

    public const DIMENSION_KEYWORD = 'keyword';

    public const DIMENSION_SEGMENT = 'segment';

    protected $fillable = [
        'tenant_id', 'client_name', 'platform', 'date', 'dimension', 'external_id', 'label',
        'campaign_id', 'campaign_name', 'impressions', 'clicks', 'spend_cents',
        'currency', 'platform_leads', 'raw_payload',
    ];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'spend_cents' => 'integer',
        'platform_leads' => 'integer',
        'raw_payload' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

}
