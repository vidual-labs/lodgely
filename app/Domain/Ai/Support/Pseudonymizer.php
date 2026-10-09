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
            'custom_answers' => $this->stripPiiKeys((array) ($lead->custom_answers ?? []), $this->identifyingValues($lead)),
            'raw_payload'    => $this->stripPiiKeys((array) ($lead->raw_payload ?? []), $this->identifyingValues($lead)),
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
     * Drop anything from a raw source payload that identifies the person.
     *
     * Key names alone are not enough: OpenFlow (and any form builder with
     * generated field ids) keys answers by opaque ids like `field_1727…`, so
     * a key-name filter let the name, email and phone straight through to
     * the model. Three passes, applied recursively:
     *
     *  1. keys whose name says PII (name, email, phone, address, …) or that
     *     carry tracking identifiers (ip, user agent, click ids, cookies);
     *  2. string values equal to the lead's own name / email / phone, or to
     *     any word of the name (a separate "First name" answer);
     *  3. string values that *look* like an email address, a phone number or
     *     an inline file upload, whatever key they sit under.
     *
     * Free text (a message saying "call me, I'm Jane") is still passed
     * through — admins who need maximum safety should disable the lead kind.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $knownValues  identifying values of this lead (lower-cased)
     * @return array<string, mixed>
     */
    public function stripPiiKeys(array $payload, array $knownValues = []): array
    {
        $piiKey = '/(name|email|phone|tel|mobile|address|street|city|zip|postal|postcode|surname|firstname|lastname'
            .'|^ip$|ip_?address|user_?agent|referr?er|^fbc$|^fbp$|gclid|gbraid|wbraid|fbclid|cookie|calon)/i';

        $walk = function (array $arr) use (&$walk, $piiKey, $knownValues): array {
            $out = [];
            foreach ($arr as $key => $value) {
                if (is_string($key) && preg_match($piiKey, $key)) {
                    continue;
                }
                if (is_array($value)) {
                    $out[$key] = $walk($value);
                    continue;
                }
                if (is_string($value) && $this->looksIdentifying($value, $knownValues)) {
                    continue;
                }
                $out[$key] = $value;
            }

            return $out;
        };

        return $walk($payload);
    }

    /**
     * @return list<string>
     */
    private function identifyingValues(Lead $lead): array
    {
        $values = [];
        foreach ([$lead->email, $lead->phone, $lead->full_name] as $value) {
            $value = mb_strtolower(trim((string) $value));
            if ($value !== '') {
                $values[] = $value;
            }
        }
        foreach (preg_split('/\s+/', mb_strtolower(trim((string) $lead->full_name))) ?: [] as $word) {
            if (mb_strlen($word) >= 2) {
                $values[] = $word;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param  list<string>  $knownValues
     */
    private function looksIdentifying(string $value, array $knownValues): bool
    {
        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return false;
        }
        if (in_array($normalized, $knownValues, true)) {
            return true;
        }
        // An email address anywhere in a short value.
        if (preg_match('/[^\s@]+@[^\s@]+\.[^\s@]+/', $value) && mb_strlen($value) <= 254) {
            return true;
        }
        // A value that is only a phone number: digits plus the usual
        // separators, long enough not to be a budget, and not an ISO date.
        if (preg_match('/^\+?[\d\s().\/-]+$/', $value)
            && preg_match_all('/\d/', $value) >= 8
            && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return true;
        }
        // An inline file upload (OpenFlow stores uploads as data: URLs).
        return str_starts_with($normalized, 'data:');
    }
}
