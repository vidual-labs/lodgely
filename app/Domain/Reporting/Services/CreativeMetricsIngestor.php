<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\DTOs\CreativeMetricsSnapshot;
use App\Models\AdCreativeReport;

class CreativeMetricsIngestor
{
    /**
     * Upsert a batch of creative snapshots into ad_creative_reports. Keyed on
     * (tenant, platform, date, dimension, external_id) so re-fetches stay
     * idempotent, mirroring {@see MetricsIngestor}.
     *
     * @param  iterable<CreativeMetricsSnapshot>  $snapshots
     * @return array{inserted: int, updated: int}
     */
    public function ingest(iterable $snapshots, int $tenantId): array
    {
        $inserted = 0;
        $updated = 0;

        foreach ($snapshots as $snap) {
            $values = [
                'label' => mb_substr($snap->label, 0, 255),
                'campaign_id' => $snap->campaignId,
                'campaign_name' => $snap->campaignName,
                'impressions' => $snap->impressions,
                'clicks' => $snap->clicks,
                'spend_cents' => $snap->spendCents,
                'currency' => $snap->currency,
                'platform_leads' => $snap->platformLeads,
                'raw_payload' => $snap->rawPayload,
            ];

            $existing = AdCreativeReport::where('tenant_id', $tenantId)
                ->where('platform', $snap->platform)
                ->where('date', $snap->date)
                ->where('dimension', $snap->dimension)
                ->where('external_id', $snap->externalId)
                ->when(
                    $snap->clientName === null,
                    fn ($q) => $q->whereNull('client_name'),
                    fn ($q) => $q->where('client_name', $snap->clientName),
                )
                ->first();

            if ($existing) {
                $existing->update($values);
                $updated++;
            } else {
                AdCreativeReport::create($values + [
                    'tenant_id' => $tenantId,
                    'client_name' => $snap->clientName,
                    'platform' => $snap->platform,
                    'date' => $snap->date,
                    'dimension' => $snap->dimension,
                    'external_id' => $snap->externalId,
                ]);
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }
}
