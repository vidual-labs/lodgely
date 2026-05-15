<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\DTOs\AdMetricsSnapshot;
use App\Models\AdSpendReport;

class MetricsIngestor
{
    /**
     * Upsert a batch of snapshots into ad_spend_reports.
     *
     * @param  iterable<AdMetricsSnapshot>  $snapshots
     * @return array{inserted: int, updated: int}
     */
    public function ingest(iterable $snapshots, int $tenantId): array
    {
        $inserted = 0;
        $updated  = 0;

        foreach ($snapshots as $snap) {
            $existing = AdSpendReport::where([
                'tenant_id'   => $tenantId,
                'platform'    => $snap->platform,
                'date'        => $snap->date,
                'campaign_id' => $snap->campaignId,
            ])->first();

            if ($existing) {
                $existing->update([
                    'campaign_name'  => $snap->campaignName,
                    'impressions'    => $snap->impressions,
                    'clicks'         => $snap->clicks,
                    'spend_cents'    => $snap->spendCents,
                    'currency'       => $snap->currency,
                    'reach'          => $snap->reach,
                    'platform_leads' => $snap->platformLeads,
                    'raw_payload'    => $snap->rawPayload,
                ]);
                $updated++;
            } else {
                AdSpendReport::create([
                    'tenant_id'      => $tenantId,
                    'platform'       => $snap->platform,
                    'date'           => $snap->date,
                    'campaign_id'    => $snap->campaignId,
                    'campaign_name'  => $snap->campaignName,
                    'impressions'    => $snap->impressions,
                    'clicks'         => $snap->clicks,
                    'spend_cents'    => $snap->spendCents,
                    'currency'       => $snap->currency,
                    'reach'          => $snap->reach,
                    'platform_leads' => $snap->platformLeads,
                    'raw_payload'    => $snap->rawPayload,
                ]);
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }
}
