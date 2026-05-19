<?php

namespace App\Importers\GoogleSheets;

use App\Importers\Contracts\IncomingLead;
use App\Importers\Contracts\LeadSource;
use App\Models\GoogleSheetSource;
use App\Models\Import;
use RuntimeException;

/**
 * Reads leads from a Google Sheet whose configuration is stored in
 * google_sheet_sources. The Import row carries the source ID in
 * meta['sheet_source_id']; this keeps the contract compatible with
 * ImportRunner without needing a new parameter.
 *
 * Column mapping is index-based: column_map["0"] = "email" etc. The
 * operator configures this via the /imports/google-sheets UI.
 */
class GoogleSheetsLeadSource implements LeadSource
{
    public function __construct(private readonly GoogleSheetsClient $client) {}

    public function key(): string
    {
        return 'google_sheets';
    }

    public function label(): string
    {
        return 'Google Sheets';
    }

    public function pull(Import $import): iterable
    {
        $sourceId = $import->meta['sheet_source_id'] ?? null;
        if (! $sourceId) {
            throw new RuntimeException('Google Sheets source: meta[sheet_source_id] is required.');
        }

        $source = GoogleSheetSource::find((int) $sourceId);
        if (! $source) {
            throw new RuntimeException("Google Sheets source: sheet source #{$sourceId} not found.");
        }

        $rows = $this->client->fetchValues($source->spreadsheet_id, $source->sheet_range);

        if (empty($rows)) {
            return;
        }

        $dataRows = $rows;
        if ($source->has_header_row) {
            array_shift($dataRows);
        }

        $columnMap = is_array($source->column_map) ? $source->column_map : [];

        foreach ($dataRows as $row) {
            $fields = $this->applyMap($row, $columnMap);

            yield new IncomingLead(
                source:       $this->key(),
                clientName:   $fields['client_name']   ?? $source->default_client_name,
                campaignName: $fields['campaign_name'] ?? $source->default_campaign_name,
                fullName:     $fields['full_name']     ?? null,
                email:        $fields['email']         ?? null,
                phone:        $fields['phone']         ?? null,
                message:      $fields['message']       ?? null,
                rawPayload:   $row,
            );
        }
    }

    /**
     * Apply the operator-configured index → field map to a raw row array.
     *
     * @param  array<int, mixed>  $row
     * @param  array<string, string>  $columnMap  {"0": "email", "1": "full_name", …}
     * @return array<string, string|null>  field → value
     */
    private function applyMap(array $row, array $columnMap): array
    {
        $result = [];

        foreach ($columnMap as $indexStr => $field) {
            if ($field === '' || $field === null) {
                continue;
            }

            $index = (int) $indexStr;
            $value = $row[$index] ?? null;

            if ($value !== null && $value !== '') {
                $result[$field] = is_string($value) ? $value : (string) $value;
            }
        }

        return $result;
    }
}
