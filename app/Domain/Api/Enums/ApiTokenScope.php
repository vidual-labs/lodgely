<?php

namespace App\Domain\Api\Enums;

/**
 * What an API token may do. Every scope is read-only and narrow on purpose:
 * a token is handed to another tool (vigilo first), so it can read exactly
 * one kind of data and nothing else.
 */
enum ApiTokenScope: string
{
    /** The lead-metadata change feed: status, flags and ad ids, never lead content. */
    case LeadMetadataRead = 'lead-metadata:read';

    public function label(): string
    {
        return match ($this) {
            self::LeadMetadataRead => __('Lead metadata (read-only, no personal data)'),
        };
    }
}
