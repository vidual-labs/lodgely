<?php

namespace App\Importers\Openflow;

use App\Importers\Contracts\IncomingLead;
use App\Importers\Contracts\LeadSource;
use App\Models\Import;
use App\Models\OpenflowSource;
use Carbon\Carbon;
use RuntimeException;
use Throwable;

/**
 * Pulls leads from a single OpenFlow form via OpenFlow's admin API.
 *
 * The Import row carries the source id in meta['openflow_source_id'], keeping
 * the LeadSource contract intact without a new parameter — same trick as the
 * Google Sheets and Meta Lead Ads adapters.
 *
 * Field mapping is operator-configured ({openflow_field_id: lead_field_key},
 * stored on OpenflowSource::field_map). Mapped fields populate the core lead
 * columns; every other answered field is preserved as a {question, answer}
 * custom answer, using the OpenFlow field label as the question — so the full
 * submission survives the trip.
 *
 * The external_id is the OpenFlow submission id scoped to its source (install +
 * form), via {@see self::scopedExternalId()} — submission ids are only unique
 * within a single form's own sequence (often small integers), so an unscoped id
 * could collide across two different forms, or the same form_id on two
 * different OpenFlow installs, and silently drop one source's leads as
 * "already ingested" duplicates of the other's. Scoping keeps dedup correctly
 * partitioned per source while remaining stable across pulls, so re-pulling the
 * same form stays idempotent. last_successful_fetch_at additionally bounds
 * incremental pulls so we don't walk the entire backlog on every scheduler tick.
 */
class OpenflowLeadSource implements LeadSource
{
    /** Submissions are fetched newest-first, this many per page. */
    private const PAGE_SIZE = 100;

    /** Hard ceiling on pages walked in a single pull, as a runaway guard. */
    private const MAX_PAGES = 200;

    /** Overlap subtracted from the high-water mark to tolerate clock skew. */
    private const OVERLAP_MINUTES = 60;

    /** Lead field keys we fold into core columns (everything else → custom answers). */
    private const CORE_FIELDS = [
        'full_name', 'email', 'phone', 'message',
        'client_name', 'campaign_name', 'status', 'priority',
    ];

    public function __construct(private readonly OpenflowClient $client) {}

    public function key(): string
    {
        return 'openflow';
    }

    public function label(): string
    {
        return 'OpenFlow';
    }

    public function pull(Import $import): iterable
    {
        $sourceId = $import->meta['openflow_source_id'] ?? null;
        if (! $sourceId) {
            throw new RuntimeException('OpenFlow source: meta[openflow_source_id] is required.');
        }

        $source = OpenflowSource::find((int) $sourceId);
        if (! $source) {
            throw new RuntimeException("OpenFlow source: source #{$sourceId} not found.");
        }

        $token = $this->authenticate($source);

        $baseUrl = $source->normalizedBaseUrl();
        $formId = (string) $source->form_id;

        // Field-id → label map, for naming custom answers. Best-effort: if the
        // form fetch fails we degrade to humanised field ids rather than abort.
        $labels = $this->fieldLabels($source, $token);

        [$coreMap, $namedAnswerMap] = $this->splitFieldMap(
            is_array($source->field_map) ? $source->field_map : []
        );

        // High-water mark: on incremental pulls, stop once we reach submissions
        // older than the last *successful* fetch (minus an overlap). Idempotency
        // still makes re-reads safe; this only bounds the work.
        //
        // Deliberately not last_fetched_at — that one is the scheduler's
        // throttle and advances on failed attempts too, which would move this
        // cutoff past submissions no pull ever ingested.
        $cutoff = $source->last_successful_fetch_at
            ? $source->last_successful_fetch_at->copy()->subMinutes(self::OVERLAP_MINUTES)
            : null;

        $page = 1;
        do {
            $result = $this->client->submissionsPage($baseUrl, $token, $formId, $page, self::PAGE_SIZE);
            $submissions = $result['submissions'];

            if ($submissions === []) {
                break;
            }

            foreach ($submissions as $submission) {
                if ($cutoff !== null && $this->isOlderThan($submission, $cutoff)) {
                    // Newest-first ordering means everything after this is older too.
                    return;
                }

                $lead = $this->toIncomingLead($submission, $source, $coreMap, $namedAnswerMap, $labels);
                if ($lead !== null) {
                    yield $lead;
                }
            }

            $page++;
            $exhausted = count($submissions) < self::PAGE_SIZE
                || ($result['total'] > 0 && $page > (int) ceil($result['total'] / self::PAGE_SIZE));
        } while (! $exhausted && $page <= self::MAX_PAGES);
    }

    /**
     * Validate connectivity and list the forms on an OpenFlow install, for the
     * configuration UI. Authenticates with the API token if given, else the
     * email/password login. Throws with a human-readable reason on failure.
     *
     * @return array<int, array{id:string, title:?string, submission_count:int}>
     */
    public function availableForms(string $baseUrl, ?string $apiToken, ?string $email, ?string $password): array
    {
        $bearer = $this->resolveBearer($baseUrl, $apiToken, $email, $password);

        return $this->client->listForms($baseUrl, $bearer);
    }

    /**
     * Fetch a form's fields for the mapping UI.
     *
     * @return array{title:?string, fields:array<int, array{id:string, label:string, type:?string}>}
     */
    public function availableFields(string $baseUrl, ?string $apiToken, ?string $email, ?string $password, string $formId): array
    {
        $bearer = $this->resolveBearer($baseUrl, $apiToken, $email, $password);

        return $this->client->formFields($baseUrl, $bearer, $formId);
    }

    private function authenticate(OpenflowSource $source): string
    {
        return $this->resolveBearer(
            $source->normalizedBaseUrl(),
            $source->apiToken(),
            $source->email,
            $source->password(),
        );
    }

    /**
     * Resolve the Bearer value to send: a read-only API token is used directly
     * (no login round-trip); otherwise we sign in with email + password and use
     * the minted JWT. Throws when neither is usable.
     */
    private function resolveBearer(string $baseUrl, ?string $apiToken, ?string $email, ?string $password): string
    {
        $apiToken = $apiToken !== null ? trim($apiToken) : '';
        if ($apiToken !== '') {
            return $apiToken;
        }

        $email = $email !== null ? trim($email) : '';
        if ($email === '' || $password === null || $password === '') {
            throw new RuntimeException(
                'OpenFlow source: no credentials. Provide an API token, or an email and password.'
            );
        }

        return $this->client->login($baseUrl, $email, $password);
    }

    /** @return array<string, string> field id → label */
    private function fieldLabels(OpenflowSource $source, string $token): array
    {
        try {
            $form = $this->client->formFields($source->normalizedBaseUrl(), $token, (string) $source->form_id);
        } catch (Throwable) {
            return [];
        }

        $labels = [];
        foreach ($form['fields'] as $field) {
            $labels[$field['id']] = $field['label'];
        }

        return $labels;
    }

    /**
     * Split the raw field map into core-column entries and named custom-answer
     * entries ("custom_answer:key").
     *
     * @param  array<string, string>  $rawMap
     * @return array{0:array<string, string>, 1:array<string, string>} [coreMap (fieldId→leadField), namedAnswerMap (fieldId→key)]
     */
    private function splitFieldMap(array $rawMap): array
    {
        $coreMap = [];
        $namedAnswerMap = [];

        foreach ($rawMap as $fieldId => $target) {
            $target = (string) $target;
            if ($target === '') {
                continue;
            }
            if (str_starts_with($target, 'custom_answer:')) {
                $key = substr($target, strlen('custom_answer:'));
                if ($key !== '') {
                    $namedAnswerMap[(string) $fieldId] = $key;
                }
            } elseif (in_array($target, self::CORE_FIELDS, true)) {
                $coreMap[(string) $fieldId] = $target;
            }
        }

        return [$coreMap, $namedAnswerMap];
    }

    /**
     * @param  array<string, mixed>  $submission
     * @param  array<string, string>  $coreMap
     * @param  array<string, string>  $namedAnswerMap
     * @param  array<string, string>  $labels
     */
    private function toIncomingLead(
        array $submission,
        OpenflowSource $source,
        array $coreMap,
        array $namedAnswerMap,
        array $labels,
    ): ?IncomingLead {
        $data = is_array($submission['data'] ?? null) ? $submission['data'] : [];

        // Apply the core column mapping.
        $fields = [];
        foreach ($coreMap as $fieldId => $leadField) {
            $value = $this->stringValue($data[$fieldId] ?? null);
            if ($value !== null) {
                $fields[$leadField] = $value;
            }
        }

        // Build custom answers: explicitly-named mappings first, then any other
        // answered field that wasn't consumed by a core mapping.
        //
        // The keys are cast back to strings on the way out: PHP silently
        // converts numeric-string array keys to ints, and OpenFlow field ids
        // often *are* numeric — without the cast the strict in_array() below
        // never matches, and every mapped field is emitted a second time as a
        // custom answer alongside the core column it populated.
        $consumed = array_map('strval', array_keys($coreMap));
        $customAnswers = [];

        foreach ($namedAnswerMap as $fieldId => $key) {
            $value = $this->stringValue($data[$fieldId] ?? null);
            if ($value === null) {
                continue;
            }
            $label = $labels[$fieldId] ?? $this->humanise($key);
            $customAnswers[] = ['question' => $label, 'answer' => $value];
            $consumed[] = (string) $fieldId;
        }

        foreach ($data as $fieldId => $raw) {
            if (in_array((string) $fieldId, $consumed, true)) {
                continue;
            }
            $value = $this->stringValue($raw);
            if ($value === null) {
                continue;
            }
            $label = $labels[(string) $fieldId] ?? $this->humanise((string) $fieldId);
            $customAnswers[] = ['question' => $label, 'answer' => $value];
        }

        $submissionId = isset($submission['id']) ? (string) $submission['id'] : '';
        $externalId = $submissionId !== '' ? self::scopedExternalId($source, $submissionId) : '';

        return new IncomingLead(
            source:        $this->key(),
            clientName:    $fields['client_name']   ?? $source->default_client_name,
            campaignName:  $fields['campaign_name'] ?? $source->default_campaign_name ?? $source->form_name,
            fullName:      $fields['full_name']     ?? null,
            email:         $fields['email']         ?? null,
            phone:         $fields['phone']         ?? null,
            message:       $fields['message']       ?? null,
            rawPayload:    $submission,
            externalId:    $externalId !== '' ? $externalId : null,
            formId:        (string) $source->form_id,
            formName:      $source->form_name,
            platform:      'openflow',
            status:        $fields['status']        ?? null,
            priority:      $fields['priority']      ?? null,
            customAnswers: $customAnswers ?: null,
        );
    }

    /**
     * Stable external_id for a submission, scoped to the OpenFlow install + form
     * it came from (not to the OpenflowSource row id, so two sources pointing at
     * the same form still dedupe against each other). The same formula is reused
     * by the rescope command to backfill leads imported before this scoping
     * existed, so keep them in lockstep.
     */
    public static function scopedExternalId(OpenflowSource $source, string $submissionId): string
    {
        return sha1($source->normalizedBaseUrl().'|'.$source->form_id).'-'.$submissionId;
    }

    /**
     * Coerce an OpenFlow answer value to a non-empty string, or null when there
     * is nothing meaningful to record. Arrays (multi-choice) join with commas.
     */
    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            $value = implode(', ', array_map(static fn ($v) => (string) $v, $value));
        }
        if (is_bool($value)) {
            $value = $value ? 'yes' : 'no';
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    private function isOlderThan(array $submission, Carbon $cutoff): bool
    {
        $created = $submission['created_at'] ?? null;
        if (! is_string($created) || $created === '') {
            return false;
        }

        try {
            return Carbon::parse($created)->lt($cutoff);
        } catch (Throwable) {
            return false;
        }
    }

    private function humanise(string $key): string
    {
        return ucfirst(trim(str_replace(['_', '-'], ' ', $key)));
    }
}
