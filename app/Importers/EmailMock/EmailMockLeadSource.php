<?php

namespace App\Importers\EmailMock;

use App\Importers\Contracts\IncomingLead;
use App\Importers\Contracts\LeadSource;
use App\Models\Import;

/**
 * Generates simulated "incoming email" leads. Useful for demos and tests
 * before a real IMAP/mail-parser backend is wired up. Operators can trigger
 * a pull manually from the UI or via the scheduled command.
 *
 * Deterministic per Import id (so re-running yields the same demo set) and
 * fully neutral — no real names, no real domains.
 */
class EmailMockLeadSource implements LeadSource
{
    private const NEUTRAL_FIRST_NAMES = [
        'Alex', 'Jordan', 'Sam', 'Casey', 'Riley', 'Morgan', 'Taylor',
        'Quinn', 'Rowan', 'Skylar', 'Hayden', 'Drew',
    ];
    private const NEUTRAL_LAST_NAMES = [
        'Bennett', 'Carter', 'Davies', 'Ellis', 'Foster', 'Gibson',
        'Harper', 'Iverson', 'Jensen', 'Knight', 'Lawson', 'Mercer',
    ];
    private const NEUTRAL_DOMAINS = [
        'example.com', 'example.net', 'demo.local', 'sample.org',
    ];
    private const NEUTRAL_MESSAGES = [
        "Hi, please send me a quote for the standard package.",
        "Looking for availability next month — is it bookable?",
        "Can you share more details on pricing and onboarding?",
        "We are evaluating options for our team, please get in touch.",
        "Interested in a callback at the earliest convenience.",
    ];

    public function key(): string
    {
        return 'email_mock';
    }

    public function label(): string
    {
        return 'Email (mock)';
    }

    public function pull(Import $import): iterable
    {
        $count = max(1, (int) ($import->meta['count'] ?? 5));
        $clientName = $import->meta['default_client_name'] ?? null;
        $campaign = $import->meta['default_campaign_name'] ?? 'Website contact form';

        // Deterministic seed → demos are reproducible per import.
        mt_srand($import->id * 1000 + $count);

        for ($i = 0; $i < $count; $i++) {
            $first = self::NEUTRAL_FIRST_NAMES[mt_rand(0, count(self::NEUTRAL_FIRST_NAMES) - 1)];
            $last  = self::NEUTRAL_LAST_NAMES[mt_rand(0, count(self::NEUTRAL_LAST_NAMES) - 1)];
            $domain = self::NEUTRAL_DOMAINS[mt_rand(0, count(self::NEUTRAL_DOMAINS) - 1)];
            $message = self::NEUTRAL_MESSAGES[mt_rand(0, count(self::NEUTRAL_MESSAGES) - 1)];

            yield new IncomingLead(
                source: $this->key(),
                clientName: $clientName,
                campaignName: $campaign,
                fullName: $first.' '.$last,
                email: mb_strtolower($first.'.'.$last.'@'.$domain),
                phone: '+49 30 '.mt_rand(1000000, 9999999),
                message: $message,
                rawPayload: [
                    'mock'    => true,
                    'subject' => 'New enquiry from website',
                    'from'    => $first.' '.$last.' <'.$first.'.'.$last.'@'.$domain.'>',
                ],
            );
        }

        mt_srand();
    }
}
