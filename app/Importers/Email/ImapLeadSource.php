<?php

namespace App\Importers\Email;

use App\Importers\Contracts\IncomingLead;
use App\Importers\Contracts\LeadSource;
use App\Models\Import;
use RuntimeException;

/**
 * Fetches unseen emails from an IMAP mailbox and converts each one into an
 * IncomingLead. Emails are marked as read after successful processing so the
 * same message is never imported twice.
 *
 * Connection is driven entirely by config('lodgely.importers.email.imap').
 * The per-import meta can override default_client_name, default_campaign_name,
 * and max_messages for one-off pulls.
 */
class ImapLeadSource implements LeadSource
{
    public function key(): string
    {
        return 'email_imap';
    }

    public function label(): string
    {
        return 'Email (IMAP)';
    }

    public function pull(Import $import): iterable
    {
        $cfg = config('lodgely.importers.email.imap');

        if (empty($cfg['host'])) {
            throw new RuntimeException('IMAP host is not configured. Set LODGELY_IMAP_HOST.');
        }

        $conn = $this->openConnection($cfg);

        try {
            yield from $this->fetchLeads($conn, $import, $cfg);
        } finally {
            imap_close($conn);
        }
    }

    /** @param array<string,mixed> $cfg */
    private function fetchLeads(\IMAP\Connection $conn, Import $import, array $cfg): iterable
    {
        $defaultClient   = ($import->meta['default_client_name'] ?? null) ?: ($cfg['default_client_name'] ?: null);
        $defaultCampaign = ($import->meta['default_campaign_name'] ?? null) ?: ($cfg['default_campaign_name'] ?: null);
        $maxMessages     = (int) ($import->meta['max_messages'] ?? $cfg['max_messages'] ?? 50);

        $msgNums = imap_search($conn, 'UNSEEN') ?: [];

        if (count($msgNums) > $maxMessages) {
            $msgNums = array_slice($msgNums, 0, $maxMessages);
        }

        $parser = new MailBodyParser();

        foreach ($msgNums as $num) {
            $headerInfo = imap_headerinfo($conn, $num);

            if ($headerInfo === false) {
                continue;
            }

            [$fromEmail, $fromName] = $this->parseFrom($headerInfo);
            $subject = isset($headerInfo->subject) ? imap_utf8($headerInfo->subject) : null;

            $bodyData = $this->fetchBody($conn, $num);
            $parsed   = $parser->parse($bodyData['text'], $bodyData['is_html']);

            // Mark read immediately — even if ingestor later rejects, we don't
            // want to re-process the same email on the next pull.
            imap_setflag_full($conn, (string) $num, '\\Seen');

            yield new IncomingLead(
                source: $this->key(),
                clientName: $defaultClient,
                campaignName: $defaultCampaign ?? $subject,
                fullName: $parsed['name'] ?? $fromName,
                email: $fromEmail,
                phone: $parsed['phone'],
                message: $parsed['message'],
                rawPayload: [
                    'from'    => $headerInfo->fromaddress ?? '',
                    'subject' => $subject ?? '',
                    'date'    => $headerInfo->date ?? '',
                ],
            );
        }
    }

    /**
     * @param array<string,mixed> $cfg
     * @return \IMAP\Connection
     */
    private function openConnection(array $cfg): mixed
    {
        $enc  = match ($cfg['encryption']) {
            'ssl'   => '/ssl',
            'tls'   => '/tls',
            default => '/notls',
        };
        $cert = $cfg['validate_cert'] ? '' : '/novalidate-cert';
        $spec = "{{$cfg['host']}:{$cfg['port']}/imap{$enc}{$cert}}{$cfg['mailbox']}";

        $conn = imap_open($spec, $cfg['username'], $cfg['password']);

        if ($conn === false) {
            throw new RuntimeException('IMAP connection failed: ' . imap_last_error());
        }

        return $conn;
    }

    /** @return array{text: string, is_html: bool} */
    private function fetchBody(\IMAP\Connection $conn, int $msgNum): array
    {
        $structure = imap_fetchstructure($conn, $msgNum);

        if ($structure === false) {
            return ['text' => '', 'is_html' => false];
        }

        // Single-part message
        if (! isset($structure->parts)) {
            $raw     = imap_fetchbody($conn, $msgNum, '1');
            $decoded = $this->decodeBody($raw, $structure->encoding ?? 0);
            $isHtml  = strtolower($structure->subtype ?? 'plain') === 'html';

            return ['text' => $decoded, 'is_html' => $isHtml];
        }

        return $this->findTextPart($conn, $msgNum, $structure->parts, '');
    }

    /**
     * Walk a multipart structure to extract the best plain-text representation.
     * Prefers text/plain; falls back to text/html (MailBodyParser will strip tags).
     *
     * @param  object[]  $parts
     * @return array{text: string, is_html: bool}
     */
    private function findTextPart(\IMAP\Connection $conn, int $msgNum, array $parts, string $prefix): array
    {
        $htmlFallback = null;

        foreach ($parts as $i => $part) {
            $partNum  = $prefix !== '' ? $prefix . '.' . ($i + 1) : (string) ($i + 1);
            $mainType = $part->type ?? 0; // 0 = TYPETEXT
            $subtype  = strtolower($part->subtype ?? '');

            if ($mainType === 0) {
                $raw     = imap_fetchbody($conn, $msgNum, $partNum);
                $decoded = $this->decodeBody($raw, $part->encoding ?? 0);

                if ($subtype === 'plain' && $decoded !== '') {
                    return ['text' => $decoded, 'is_html' => false];
                }

                if ($subtype === 'html' && $decoded !== '' && $htmlFallback === null) {
                    $htmlFallback = ['text' => $decoded, 'is_html' => true];
                }
            } elseif (isset($part->parts)) {
                $nested = $this->findTextPart($conn, $msgNum, $part->parts, $partNum);

                // Only short-circuit on an actual hit — a nested subtree with no
                // text (e.g. multipart/related holding only images) must not
                // shadow a text/plain sibling that comes after it.
                if (! $nested['is_html'] && $nested['text'] !== '') {
                    return $nested;
                }

                if ($nested['text'] !== '') {
                    $htmlFallback ??= $nested;
                }
            }
        }

        return $htmlFallback ?? ['text' => '', 'is_html' => false];
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body),          // BASE64
            4 => quoted_printable_decode($body), // QUOTED-PRINTABLE
            default => $body,
        };
    }

    /** @return array{string|null, string|null} [email, name] */
    private function parseFrom(object $headerInfo): array
    {
        $from = $headerInfo->from[0] ?? null;

        if ($from === null) {
            return [null, null];
        }

        $email = isset($from->mailbox, $from->host)
            ? strtolower($from->mailbox . '@' . $from->host)
            : null;

        $name = isset($from->personal) && $from->personal !== ''
            ? imap_utf8($from->personal)
            : null;

        return [$email, $name];
    }
}
