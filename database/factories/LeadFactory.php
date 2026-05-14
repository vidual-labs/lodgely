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
}
