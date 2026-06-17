<?php

namespace App\Importers\Meta;

use App\Importers\Contracts\IncomingLead;
use App\Importers\Contracts\LeadSource;
use App\Models\AdPlatformSetting;
use App\Models\Import;
use App\Models\MetaLeadSource;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Live Meta (Facebook/Instagram) Lead Ads adapter — pulls individual leads
 * straight from the Graph API instead of routing them through a Google Sheet.
 *
 * Reuses the Meta credentials configured in Settings → Ad platforms (the same
 * row {@see MetaAdsSource} uses for reporting). The access token must additionally
 * carry the `leads_retrieval` permission plus access to the Page that owns the
 * lead gen forms.
 *
 * The Import row carries the source id in meta['meta_lead_source_id'], keeping
 * the LeadSource contract intact without a new parameter — same trick as the
 * Google Sheets adapter.
 *
 * Standard Meta field names map onto the core lead columns; every other answer
 * is preserved as a {question, answer} custom answer. The Meta lead id is the
 * stable external_id, so re-pulling the same window is idempotent — the
 * LeadIngestor recognises and skips leads it has already seen.
 */
class MetaLeadsSource implements LeadSource
{
    /** field_data names we fold into the core lead columns (not custom answers). */
    private const EMAIL_FIELDS = ['email', 'work_email', 'company_email'];

    private const PHONE_FIELDS = ['phone_number', 'phone', 'mobile_number'];

    private const FULL_NAME_FIELDS = ['full_name', 'name'];

    private const FIRST_NAME_FIELDS = ['first_name', 'given_name'];

    private const LAST_NAME_FIELDS = ['last_name', 'family_name', 'surname'];

    private const MESSAGE_FIELDS = ['message', 'comments', 'note', 'notes'];

    public function key(): string
    {
        return 'meta_leads';
    }

    public function label(): string
    {
        return 'Meta Lead Ads';
    }

    public function pull(Import $import): iterable
    {
        $sourceId = $import->meta['meta_lead_source_id'] ?? null;
        if (! $sourceId) {
            throw new RuntimeException('Meta Lead Ads source: meta[meta_lead_source_id] is required.');
        }

        $source = MetaLeadSource::find((int) $sourceId);
        if (! $source) {
            throw new RuntimeException("Meta Lead Ads source: source #{$sourceId} not found.");
        }

        [$token, $apiVer] = $this->credentials($import->tenant_id);

        $forms = $this->resolveForms($source, $apiVer, $token);

        $since = $source->lookback_days > 0
            ? now()->subDays($source->lookback_days)->getTimestamp()
            : null;

        foreach ($forms as $form) {
            yield from $this->fetchFormLeads($form, $source, $apiVer, $token, $since);
        }
    }

    /**
     * List the lead gen forms on a page, for the configuration UI to confirm
     * connectivity and let the operator pin a single form.
     *
     * @return array<int, array{id:string, name:?string, status:?string}>
     */
    public function availableForms(int $tenantId, string $pageId): array
    {
        $pageId = trim($pageId);
        if ($pageId === '') {
            throw new RuntimeException('Enter a Page ID before loading forms.');
        }

        [$token, $apiVer] = $this->credentials($tenantId);

        $url = sprintf('https://graph.facebook.com/%s/%s/leadgen_forms', $apiVer, $pageId);

        $forms = [];
        foreach ($this->requestPaged($url, ['fields' => 'id,name,status', 'access_token' => $token, 'limit' => 200]) as $form) {
            $id = (string) ($form['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $forms[] = [
                'id'     => $id,
                'name'   => isset($form['name']) ? (string) $form['name'] : null,
                'status' => isset($form['status']) ? (string) $form['status'] : null,
            ];
        }

        return $forms;
    }

    /** @return array{0:string, 1:string} [token, apiVersion] */
    private function credentials(int $tenantId): array
    {
        $settings = AdPlatformSetting::resolveSafe($tenantId);

        $token = trim($settings->effectiveMetaAccessToken());
        $apiVer = trim($settings->effectiveMetaApiVersion());

        if ($token === '') {
            throw new RuntimeException(
                'Meta Lead Ads source: no Meta access token configured. Connect Meta under Settings → Ad platforms first.'
            );
        }

        return [$token, $apiVer ?: 'v21.0'];
    }

    /**
     * Resolve which forms to pull: the pinned form_id if set, otherwise every
     * active lead gen form on the configured page.
     *
     * @return array<int, array{id:string, name:?string}>
     */
    private function resolveForms(MetaLeadSource $source, string $apiVer, string $token): array
    {
        if (trim((string) $source->form_id) !== '') {
            return [['id' => trim((string) $source->form_id), 'name' => $source->form_name]];
        }

        $pageId = trim((string) $source->page_id);
        if ($pageId === '') {
            throw new RuntimeException('Meta Lead Ads source: a Page ID or Form ID is required.');
        }

        $forms = [];
        foreach ($this->availableForms($source->tenant_id, $pageId) as $form) {
            $forms[] = ['id' => $form['id'], 'name' => $form['name']];
        }

        return $forms;
    }

    /**
     * @param  array{id:string, name:?string}  $form
     * @return iterable<IncomingLead>
     */
    private function fetchFormLeads(array $form, MetaLeadSource $source, string $apiVer, string $token, ?int $since): iterable
    {
        $url = sprintf('https://graph.facebook.com/%s/%s/leads', $apiVer, $form['id']);

        $params = [
            'fields'       => 'id,created_time,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id,platform,is_organic,field_data',
            'access_token' => $token,
            'limit'        => 200,
        ];

        if ($since !== null) {
            $params['filtering'] = json_encode([[
                'field'    => 'time_created',
                'operator' => 'GREATER_THAN',
                'value'    => $since,
            ]]);
        }

        foreach ($this->requestPaged($url, $params) as $lead) {
            yield $this->toIncomingLead($lead, $form, $source);
        }
    }

    /**
     * @param  array<string, mixed>  $lead
     * @param  array{id:string, name:?string}  $form
     */
    private function toIncomingLead(array $lead, array $form, MetaLeadSource $source): IncomingLead
    {
        $fields = $this->flattenFieldData($lead['field_data'] ?? []);

        $email = $this->firstOf($fields, self::EMAIL_FIELDS);
        $phone = $this->firstOf($fields, self::PHONE_FIELDS);

        $fullName = $this->firstOf($fields, self::FULL_NAME_FIELDS);
        if ($fullName === null) {
            $first = $this->firstOf($fields, self::FIRST_NAME_FIELDS);
            $last = $this->firstOf($fields, self::LAST_NAME_FIELDS);
            $combined = trim(($first ?? '').' '.($last ?? ''));
            $fullName = $combined !== '' ? $combined : null;
        }

        $message = $this->firstOf($fields, self::MESSAGE_FIELDS);

        // Anything not consumed above is surfaced as a custom answer, using the
        // Meta field name (humanised) as the question label.
        $consumed = array_merge(
            self::EMAIL_FIELDS, self::PHONE_FIELDS, self::FULL_NAME_FIELDS,
            self::FIRST_NAME_FIELDS, self::LAST_NAME_FIELDS, self::MESSAGE_FIELDS,
        );
        $customAnswers = [];
        foreach ($fields as $name => $value) {
            if ($value === null || $value === '' || in_array($name, $consumed, true)) {
                continue;
            }
            $customAnswers[] = ['question' => $this->humanise($name), 'answer' => $value];
        }

        $adId = isset($lead['ad_id']) ? (string) $lead['ad_id'] : null;
        $isOrganic = array_key_exists('is_organic', $lead)
            ? (bool) $lead['is_organic']
            : ($adId === null);

        $leadId = isset($lead['id']) ? (string) $lead['id'] : '';

        return new IncomingLead(
            source:        $this->key(),
            clientName:    $source->default_client_name,
            campaignName:  $lead['campaign_name'] ?? $source->default_campaign_name,
            fullName:      $fullName,
            email:         $email,
            phone:         $phone,
            message:       $message,
            rawPayload:    $lead,
            externalId:    $leadId !== '' ? $leadId : null,
            metaLeadId:    $leadId !== '' ? $leadId : null,
            adId:          $adId,
            adName:        isset($lead['ad_name']) ? (string) $lead['ad_name'] : null,
            adsetId:       isset($lead['adset_id']) ? (string) $lead['adset_id'] : null,
            adsetName:     isset($lead['adset_name']) ? (string) $lead['adset_name'] : null,
            campaignId:    isset($lead['campaign_id']) ? (string) $lead['campaign_id'] : null,
            formId:        $this->resolveFormId($lead, $form),
            formName:      $form['name'] ?? null,
            platform:      $this->normalisePlatform($lead['platform'] ?? null),
            isOrganic:     $isOrganic,
            customAnswers: $customAnswers ?: null,
        );
    }

    /**
     * Flatten Meta's field_data array ([{name, values:[…]}, …]) into a simple
     * name => first-value map.
     *
     * @param  array<int, mixed>  $fieldData
     * @return array<string, string>
     */
    private function flattenFieldData(array $fieldData): array
    {
        $fields = [];
        foreach ($fieldData as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $name = isset($entry['name']) ? (string) $entry['name'] : '';
            if ($name === '') {
                continue;
            }
            $value = $entry['values'][0] ?? null;
            if (is_array($value)) {
                $value = implode(', ', array_map(static fn ($v) => (string) $v, $value));
            }
            if ($value !== null && $value !== '') {
                $fields[$name] = (string) $value;
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<int, string>  $candidates
     */
    private function firstOf(array $fields, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($fields[$candidate]) && $fields[$candidate] !== '') {
                return $fields[$candidate];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $lead
     * @param  array{id:string, name:?string}  $form
     */
    private function resolveFormId(array $lead, array $form): ?string
    {
        $id = isset($lead['form_id']) ? (string) $lead['form_id'] : '';
        if ($id === '') {
            $id = (string) ($form['id'] ?? '');
        }

        return $id !== '' ? $id : null;
    }

    private function normalisePlatform(mixed $platform): ?string
    {
        return match (strtolower((string) $platform)) {
            'fb', 'facebook' => 'facebook',
            'ig', 'instagram' => 'instagram',
            '' => null,
            default => (string) $platform,
        };
    }

    private function humanise(string $key): string
    {
        return ucfirst(trim(str_replace('_', ' ', $key)));
    }

    /**
     * Walk Graph API pages, yielding each `data` row. Mirrors the paging loop
     * in {@see MetaAdsSource} (absolute `paging.next` URLs already carry their
     * own query string, so params are only sent on the first request).
     *
     * @param  array<string, mixed>  $params
     * @return iterable<array<string, mixed>>
     */
    private function requestPaged(string $url, array $params): iterable
    {
        $timeout = (int) config('lodgely.reporting.http_timeout_sec', 30);

        do {
            $response = Http::timeout($timeout)
                ->retry(2, 500, throw: false)
                ->acceptJson()
                ->get($url, $params);

            if (! $response->successful()) {
                throw new RuntimeException(sprintf(
                    'Meta Lead Ads call failed (%d): %s',
                    $response->status(),
                    substr($response->body(), 0, 400),
                ));
            }

            $json = $response->json();

            foreach (($json['data'] ?? []) as $row) {
                if (is_array($row)) {
                    yield $row;
                }
            }

            $url = $json['paging']['next'] ?? null;
            $params = [];
        } while ($url);
    }
}
