<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSpendReport extends Model
{
    protected $fillable = [
        'tenant_id', 'platform', 'date', 'campaign_id', 'campaign_name',
        'impressions', 'clicks', 'spend_cents', 'currency', 'reach',
        'platform_leads', 'raw_payload',
    ];

    protected $casts = [
        'date'           => 'date',
        'impressions'    => 'integer',
        'clicks'         => 'integer',
        'spend_cents'    => 'integer',
        'reach'          => 'integer',
        'platform_leads' => 'integer',
        'raw_payload'    => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function spendFormatted(): string
    {
        return sprintf('%s %.2f', strtoupper($this->currency), $this->spend_cents / 100);
    }
}
