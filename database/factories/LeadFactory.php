<?php

namespace Database\Factories;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 *
 * Neutral demo data — no real customer names, no real campaigns.
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /** Generic, neutral example "clients" — could be agency clients OR inhouse brands. */
    private const CLIENTS = [
        'Northwind Studio', 'Acme Wellness', 'Atlas Coaching',
        'Riverstone Bakery', 'Polaris Dental', 'Sunset Studios',
    ];

    private const CAMPAIGNS = [
        'Website contact form', 'Newsletter signup', 'Landing page A',
        'Spring outreach', 'Quarterly export', 'Referral programme',
    ];

    private const MESSAGES = [
        "Hi, please send me a quote for the standard package.",
        "We are evaluating providers — could you share an overview?",
        "Looking for a callback this week if possible.",
        "Interested in the introductory offer mentioned on your page.",
        "Could you confirm availability for early next month?",
        null, null, // some leads come without a message
    ];

    /**
     * Meta Lead Ads — sample form/ad combos so the inbox shows a realistic
     * spread of campaigns and forms (not just one stock entry).
     *
     * @var array<int, array<string, string|bool>>
     */
    private const META_FORMS = [
        [
            'campaign_name' => 'Spring promo · IG reels',
            'ad_name'       => 'IG Reel — 30s before/after',
            'adset_name'    => 'Adset · women 28–55 · DE',
            'form_name'     => 'Spring 2026 — Free consultation',
            'platform'      => 'instagram',
            'is_organic'    => false,
        ],
        [
            'campaign_name' => 'Always-on lead form · FB',
            'ad_name'       => 'FB Single Image — testimonial',
            'adset_name'    => 'Adset · lookalike 1% NL+DE',
            'form_name'     => 'Get a quote',
            'platform'      => 'facebook',
            'is_organic'    => false,
        ],
        [
            'campaign_name' => 'Webinar Q2',
            'ad_name'       => 'FB Video — 60s walkthrough',
            'adset_name'    => 'Adset · interest · self-employed',
            'form_name'     => 'Reserve my webinar seat',
            'platform'      => 'facebook',
            'is_organic'    => false,
        ],
        [
            'campaign_name' => 'IG bio · organic form',
            'ad_name'       => null,
            'adset_name'    => null,
            'form_name'     => 'Book a free intro call',
            'platform'      => 'instagram',
            'is_organic'    => true,
        ],
    ];

    public function definition(): array
    {
        $first = $this->faker->firstName();
        $last  = $this->faker->lastName();
        $email = mb_strtolower("{$first}.{$last}.".$this->faker->randomNumber(4)."@" . $this->faker->randomElement(['example.com', 'example.net', 'demo.local', 'sample.org']));
        $phone = '+49 30 ' . $this->faker->numerify('#######');

        return [
            'tenant_id'       => Tenant::DEFAULT_ID,
            'source'          => $this->faker->randomElement(['csv', 'email_mock', 'manual']),
            'client_name'     => $this->faker->randomElement(self::CLIENTS),
            'campaign_name'   => $this->faker->randomElement(self::CAMPAIGNS),
            'full_name'       => "{$first} {$last}",
            'email'           => $email,
            'phone'           => $phone,
            'email_normalized'=> $email,
            'phone_normalized'=> preg_replace('/\D+/', '', $phone),
            'message'         => $this->faker->randomElement(self::MESSAGES),
            'status'          => $this->faker->randomElement([
                LeadStatus::New->value, LeadStatus::New->value, LeadStatus::New->value,
                LeadStatus::Reviewed->value, LeadStatus::Incomplete->value, LeadStatus::Forwarded->value,
            ]),
            'priority'        => $this->faker->randomElement([
                LeadPriority::Low->value, LeadPriority::Medium->value, LeadPriority::Medium->value, LeadPriority::High->value,
            ]),
            'duplicate_flag'  => false,
            'retention_until' => now()->addYear(),
            'created_at'      => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Demo lead originating from Meta Lead Ads — fills the Meta attribution fields.
     */
    public function meta(): static
    {
        return $this->state(function () {
            $form = $this->faker->randomElement(self::META_FORMS);

            return [
                'source'        => 'meta_ads',
                'campaign_name' => $form['campaign_name'],
                'ad_name'       => $form['ad_name'],
                'adset_name'    => $form['adset_name'],
                'form_name'     => $form['form_name'],
                'platform'      => $form['platform'],
                'is_organic'    => $form['is_organic'],
                'meta_lead_id'  => (string) $this->faker->numerify('##############'),
                'ad_id'         => $form['ad_name']    ? (string) $this->faker->numerify('############') : null,
                'adset_id'      => $form['adset_name'] ? (string) $this->faker->numerify('############') : null,
                'campaign_id'   => (string) $this->faker->numerify('############'),
                'form_id'       => (string) $this->faker->numerify('############'),
                'custom_answers'=> null,
                'message'       => null,
            ];
        });
    }
}
