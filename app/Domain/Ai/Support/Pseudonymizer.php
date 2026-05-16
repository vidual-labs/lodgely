<?php

namespace App\Domain\Ai\Support;

use App\Models\Lead;

/**
 * Masks lead-level PII before it is serialized into a prompt. Only used
 * for the `lead_qualification` kind — aggregate kinds (`report_view`)
 * never see lead rows.
 *
 * The contract: anything that could re-identify a person to a third-party
 * model should be masked. The lead's intent (message body, campaign
 * labels, ad context) is passed through because that is what the model
 * needs to reason about quality.
 */
class Pseudonymizer
{
    /** @return array<string, mixed> */
    public function maskedLead(Lead $lead): array
    {
        return [
            'lead_ref'       => 'Lead #'.$lead->id,
            'email'          => $this->maskEmail($lead->email),
            'phone'          => $this->maskPhone($lead->phone),
            'message'        => $lead->message,
            'client_name'    => $lead->client_name,
            'campaign_name'  => $lead->campaign_name,
            'campaign_id'    => $lead->campaign_id,
            'ad_name'        => $lead->ad_name,
            'adset_name'     => $lead->adset_name,
            'form_name'      => $lead->form_name,
            'platform'       => $lead->platform,
            'source'         => $lead->source,
            'is_organic'     => $lead->is_organic,
            'received_at'    => $lead->created_at?->toIso8601String(),
            'current_status' => $lead->status?->value,
            'current_priority' => $lead->priority?->value,
            'raw_payload'    => $this->stripPiiKeys((array) ($lead->raw_payload ?? [])),
        ];
    }

    public function maskEmail(?string $email): ?string
    {
        if (! $email) {
            return null;
        }
        $at = strrpos($email, '@');
        if ($at === false || $at < 1) {
            return '***';
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $first  = mb_substr($local, 0, 1);

        return $first.'***@'.$domain;
    }

    public function maskPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '***';
        }
        $last = substr($digits, -2);
        $cc   = strlen($digits) > 4 ? substr($digits, 0, max(1, strlen($digits) - 9)) : '';

        return ($cc ? '+'.$cc.' ' : '').'*** '.$last;
    }

    /**
     * Drop keys from a raw form payload that obviously carry PII. We don't
     * try to detect PII inside free-form values — admins who want maximum
     * safety should disable the lead kind entirely.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function stripPiiKeys(array $payload): array
    {
        $pii = '/(name|email|phone|tel|mobile|address|street|city|zip|postal|postcode|surname|firstname|lastname)/i';

        $walk = function (array $arr) use (&$walk, $pii): array {
            $out = [];
            foreach ($arr as $key => $value) {
                if (is_string($key) && preg_match($pii, $key)) {
                    continue;
                }
                $out[$key] = is_array($value) ? $walk($value) : $value;
            }

            return $out;
        };

        return $walk($payload);
    }
}
