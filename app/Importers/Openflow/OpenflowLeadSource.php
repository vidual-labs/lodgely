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
 * Each OpenFlow submission id is the stable external_id, so re-pulling the same
 * form is idempotent: the LeadIngestor recognises and skips submissions it has
 * already ingested. last_fetched_at additionally bounds incremental pulls so we
 * don't walk the entire backlog on every scheduler tick.
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
        // older than the last fetch (minus an overlap). Idempotency still makes
        // re-reads safe; this only bounds the work.
        $cutoff = $source->last_fetched_at
            ? $source->last_fetched_at->copy()->subMinutes(self::OVERLAP_MINUTES)
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
     * configuration UI. Throws with a human-readable reason on failure.
     *
     * @return array<int, array{id:string, title:?string, submission_count:int}>
     */
    public function availableForms(string $baseUrl, string $email, string $password): array
    {
        $token = $this->client->login($baseUrl, $email, $password);

        return $this->client->listForms($baseUrl, $token);
    }

    /**
     * Fetch a form's fields for the mapping UI.
     *
     * @return array{title:?string, fields:array<int, array{id:string, label:string, type:?string}>}
     */
    public function availableFields(string $baseUrl, string $email, string $password, string $formId): array
    {
        $token = $this->client->login($baseUrl, $email, $password);

        return $this->client->formFields($baseUrl, $token, $formId);
    }

    private function authenticate(OpenflowSource $source): string
    {
        $password = $source->password();
        if ($password === null) {
            throw new RuntimeException('OpenFlow source: no password stored. Edit the source and re-enter the password.');
        }

        return $this->client->login($source->normalizedBaseUrl(), (string) $source->email, $password);
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
        $consumed = array_keys($coreMap);
        $customAnswers = [];

        foreach ($namedAnswerMap as $fieldId => $key) {
            $value = $this->stringValue($data[$fieldId] ?? null);
            if ($value === null) {
                continue;
            }
            $label = $labels[$fieldId] ?? $this->humanise($key);
            $customAnswers[] = ['question' => $label, 'answer' => $value];
            $consumed[] = $fieldId;
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

        $externalId = isset($submission['id']) ? (string) $submission['id'] : '';

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
