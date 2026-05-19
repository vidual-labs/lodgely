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

        // Capture the header row (if any) so custom-answer columns can use the
        // operator's sheet column names as their question labels in the inbox.
        $headerRow = $source->has_header_row ? ($rows[0] ?? []) : [];

        $dataRows = $rows;
        if ($source->has_header_row) {
            array_shift($dataRows);
        }

        $rawMap = is_array($source->column_map) ? $source->column_map : [];

        // Split regular field entries from named custom_answer:* entries.
        $columnMap = [];
        $namedAnswerMap = []; // column index (int) => custom_answers key
        foreach ($rawMap as $indexStr => $fieldValue) {
            if (str_starts_with((string) $fieldValue, 'custom_answer:')) {
                $key = substr((string) $fieldValue, strlen('custom_answer:'));
                if ($key !== '') {
                    $namedAnswerMap[(int) $indexStr] = $key;
                }
            } else {
                $columnMap[$indexStr] = $fieldValue;
            }
        }

        // Reverse lookup: field key → column index (for resolving the header label
        // of a fixed custom-answer field like utm_source or question_01).
        $fieldToIndex = [];
        foreach ($columnMap as $indexStr => $field) {
            $fieldToIndex[$field] = (int) $indexStr;
        }

        $customAnswerKeys = [
            'is_quality', 'is_converted',
            'created_time',
            'question_01', 'question_02', 'question_03', 'question_04',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        ];

        $leadFieldLabels = GoogleSheetSource::leadFields();

        foreach ($dataRows as $row) {
            $fields = $this->applyMap($row, $columnMap);

            // Build custom answers as a list of {question, answer} objects so they
            // surface in the inbox column picker and lead-detail panel.
            $customAnswers = [];
            foreach ($customAnswerKeys as $key) {
                if (! isset($fields[$key]) || $fields[$key] === '') {
                    continue;
                }
                $customAnswers[] = [
                    'question' => $this->labelFor($key, $fieldToIndex, $headerRow, $leadFieldLabels),
                    'answer'   => (string) $fields[$key],
                ];
            }

            // Populate named custom answers (custom_answer:key_name mapping).
            foreach ($namedAnswerMap as $index => $key) {
                $value = $row[$index] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $header = isset($headerRow[$index]) ? trim((string) $headerRow[$index]) : '';
                $customAnswers[] = [
                    'question' => $header !== '' ? $header : $this->humaniseKey($key),
                    'answer'   => is_string($value) ? $value : (string) $value,
                ];
            }

            yield new IncomingLead(
                source:        $fields['source']        ?? $this->key(),
                clientName:    $fields['client_name']   ?? $source->default_client_name,
                campaignName:  $fields['campaign_name'] ?? $source->default_campaign_name,
                fullName:      $fields['full_name']     ?? null,
                email:         $fields['email']         ?? null,
                phone:         $fields['phone']         ?? null,
                message:       $fields['message']       ?? null,
                rawPayload:    $row,
                metaLeadId:    $fields['lead_id']       ?? null,
                formId:        $fields['form_id']       ?? null,
                platform:      $fields['platform']      ?? null,
                status:        $fields['status']        ?? null,
                priority:      $fields['priority']      ?? null,
                isQualified:   $this->isTruthy($fields['is_qualified'] ?? null),
                isCalled:      $this->isTruthy($fields['is_called']    ?? null),
                isMailed:      $this->isTruthy($fields['is_mailed']    ?? null),
                customAnswers: $customAnswers ?: null,
            );
        }
    }

    /**
     * Question label for a fixed custom-answer field: prefer the operator's
     * sheet column header, fall back to the static label from leadFields().
     *
     * @param  array<string, int>  $fieldToIndex
     * @param  array<int, mixed>   $headerRow
     * @param  array<string, string>  $labels
     */
    private function labelFor(string $field, array $fieldToIndex, array $headerRow, array $labels): string
    {
        if (isset($fieldToIndex[$field], $headerRow[$fieldToIndex[$field]])) {
            $header = trim((string) $headerRow[$fieldToIndex[$field]]);
            if ($header !== '') {
                return $header;
            }
        }

        return $labels[$field] ?? $field;
    }

    private function humaniseKey(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    private function isTruthy(?string $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return in_array(strtolower($value), ['1', 'yes', 'true', 'x', 'y', 'ja'], true);
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
