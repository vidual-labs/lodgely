<?php

namespace App\Importers\Csv;

use App\Importers\Contracts\IncomingLead;
use App\Importers\Contracts\LeadSource;
use App\Models\Import;
use League\Csv\Reader;
use League\Csv\Statement;

/**
 * Reads leads from a CSV file referenced by the Import row. Mapping is
 * by header name, case-insensitive, with a small set of accepted aliases
 * so common exports (HubSpot/Mailchimp/Notion/Excel) drop in without prep.
 */
class CsvLeadSource implements LeadSource
{
    public function key(): string
    {
        return 'csv';
    }

    public function label(): string
    {
        return 'CSV import';
    }

    /** Maps logical lead field → list of accepted header names (lowercase). */
    private const HEADER_ALIASES = [
        'full_name'     => ['full_name', 'full name', 'name', 'contact', 'contact name'],
        'email'         => ['email', 'email address', 'e-mail', 'mail'],
        'phone'         => ['phone', 'phone number', 'tel', 'telephone', 'mobile'],
        'message'       => ['message', 'note', 'comment', 'enquiry', 'inquiry'],
        'client_name'   => ['client', 'client_name', 'client name', 'brand', 'account'],
        'campaign_name' => ['campaign', 'campaign_name', 'campaign name', 'source campaign'],
    ];

    public function pull(Import $import): iterable
    {
        $path = $import->meta['path'] ?? null;
        if (! $path || ! is_file($path)) {
            return;
        }

        $reader = Reader::from($path);
        $reader->setHeaderOffset(0);

        $records = (new Statement())->limit(config('lodgely.importers.csv.max_rows'))->process($reader);

        $headerMap = $this->buildHeaderMap($reader->getHeader());

        foreach ($records as $row) {
            yield new IncomingLead(
                source: $this->key(),
                clientName:   $this->pluck($row, $headerMap, 'client_name')   ?? ($import->meta['default_client_name'] ?? null),
                campaignName: $this->pluck($row, $headerMap, 'campaign_name') ?? ($import->meta['default_campaign_name'] ?? null),
                fullName:     $this->pluck($row, $headerMap, 'full_name'),
                email:        $this->pluck($row, $headerMap, 'email'),
                phone:        $this->pluck($row, $headerMap, 'phone'),
                message:      $this->pluck($row, $headerMap, 'message'),
                rawPayload:   $row,
            );
        }
    }

    /** @param  array<int, string>  $headers
     *  @return array<string, string|null> logical field → actual header */
    private function buildHeaderMap(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $h) {
            $normalized[mb_strtolower(trim($h))] = $h;
        }

        $map = [];
        foreach (self::HEADER_ALIASES as $logical => $aliases) {
            $map[$logical] = null;
            foreach ($aliases as $alias) {
                if (isset($normalized[$alias])) {
                    $map[$logical] = $normalized[$alias];
                    break;
                }
            }
        }

        return $map;
    }

    /** @param  array<string, mixed>  $row
     *  @param  array<string, string|null>  $map */
    private function pluck(array $row, array $map, string $logical): ?string
    {
        $header = $map[$logical] ?? null;
        if ($header === null || ! array_key_exists($header, $row)) {
            return null;
        }
        $value = $row[$header];

        return is_string($value) ? $value : (is_scalar($value) ? (string) $value : null);
    }
}
