<?php

namespace App\Importers\Openflow;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP client for an OpenFlow install's admin API.
 *
 * OpenFlow has no API token — authentication is a JWT minted from an
 * email/password login (`POST /api/auth/login`). The token comes back as an
 * httpOnly `token` cookie (the JSON body only carries the user), so login()
 * scrapes it from the Set-Cookie jar and subsequent calls send it as a Bearer
 * token, which OpenFlow's auth middleware also accepts.
 *
 * Stateless and credential-free by construction: every method takes the base
 * URL and (where needed) a token, so the same singleton serves every source.
 */
class OpenflowClient
{
    private function timeout(): int
    {
        return (int) config('lodgely.importers.openflow.http_timeout_sec', 30);
    }

    /**
     * Log in and return a JWT usable as a Bearer token. Throws on bad
     * credentials or an unreachable host.
     */
    public function login(string $baseUrl, string $email, string $password): string
    {
        $url = $this->normalize($baseUrl).'/api/auth/login';

        $response = Http::timeout($this->timeout())
            ->acceptJson()
            ->asJson()
            ->post($url, ['email' => $email, 'password' => $password]);

        if ($response->status() === 401) {
            throw new RuntimeException('OpenFlow login failed: invalid email or password.');
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'OpenFlow login failed (%d): %s',
                $response->status(),
                substr($response->body(), 0, 300),
            ));
        }

        // Preferred path: the httpOnly `token` cookie set on a successful login.
        foreach ($response->cookies()?->toArray() ?? [] as $cookie) {
            if (($cookie['Name'] ?? null) === 'token' && ! empty($cookie['Value'])) {
                return (string) $cookie['Value'];
            }
        }

        // Fallback: some reverse-proxied installs surface the token in the body.
        $token = $response->json('token');
        if (is_string($token) && $token !== '') {
            return $token;
        }

        throw new RuntimeException(
            'OpenFlow login succeeded but no auth token was returned. '
            .'Check that the base URL points at the OpenFlow API, not a proxy that strips cookies.'
        );
    }

    /**
     * List the forms the logged-in user owns, for the configuration UI.
     *
     * @return array<int, array{id:string, title:?string, submission_count:int}>
     */
    public function listForms(string $baseUrl, string $token): array
    {
        $json = $this->get($baseUrl, $token, '/api/forms');

        $forms = [];
        foreach (($json['forms'] ?? []) as $form) {
            if (! is_array($form) || empty($form['id'])) {
                continue;
            }
            $forms[] = [
                'id'               => (string) $form['id'],
                'title'            => isset($form['title']) ? (string) $form['title'] : null,
                'submission_count' => (int) ($form['submission_count'] ?? 0),
            ];
        }

        return $forms;
    }

    /**
     * Fetch a single form, returning its flattened leaf fields (groups
     * expanded) as a list of {id, label, type}. Drives the mapping UI.
     *
     * @return array{title:?string, fields:array<int, array{id:string, label:string, type:?string}>}
     */
    public function formFields(string $baseUrl, string $token, string $formId): array
    {
        $json = $this->get($baseUrl, $token, '/api/forms/'.rawurlencode($formId));
        $form = $json['form'] ?? null;

        if (! is_array($form)) {
            throw new RuntimeException('OpenFlow form response was malformed.');
        }

        $fields = [];
        foreach ($this->flattenSteps($form['steps'] ?? []) as $field) {
            $id = (string) ($field['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $label = trim((string) ($field['label'] ?? $field['question'] ?? ''));
            $fields[] = [
                'id'    => $id,
                'label' => $label !== '' ? $label : $id,
                'type'  => isset($field['type']) ? (string) $field['type'] : null,
            ];
        }

        return [
            'title'  => isset($form['title']) ? (string) $form['title'] : null,
            'fields' => $fields,
        ];
    }

    /**
     * Fetch one page of submissions (newest first), as returned by
     * `GET /api/submissions/:formId`.
     *
     * @return array{submissions:array<int, array<string, mixed>>, total:int, page:int, limit:int}
     */
    public function submissionsPage(string $baseUrl, string $token, string $formId, int $page, int $limit): array
    {
        $json = $this->get($baseUrl, $token, '/api/submissions/'.rawurlencode($formId), [
            'page'  => $page,
            'limit' => $limit,
        ]);

        $submissions = [];
        foreach (($json['submissions'] ?? []) as $row) {
            if (is_array($row)) {
                $submissions[] = $row;
            }
        }

        return [
            'submissions' => $submissions,
            'total'       => (int) ($json['total'] ?? count($submissions)),
            'page'        => (int) ($json['page'] ?? $page),
            'limit'       => (int) ($json['limit'] ?? $limit),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $baseUrl, string $token, string $path, array $query = []): array
    {
        $url = $this->normalize($baseUrl).$path;

        $response = Http::timeout($this->timeout())
            ->retry(2, 500, throw: false)
            ->withToken($token)
            ->acceptJson()
            ->get($url, $query);

        if ($response->status() === 401) {
            throw new RuntimeException('OpenFlow request rejected: the session token was not accepted.');
        }

        if ($response->status() === 404) {
            throw new RuntimeException('OpenFlow returned 404 for '.$path.' — check the form ID and that this account owns the form.');
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'OpenFlow request to %s failed (%d): %s',
                $path,
                $response->status(),
                substr($response->body(), 0, 300),
            ));
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Flatten OpenFlow's steps array (a step may be a single field, or a
     * `group` with a nested `fields[]`) into a flat list of leaf fields.
     * Mirrors OpenFlow's own utils/steps.flattenFields.
     *
     * @param  array<int, mixed>  $steps
     * @return array<int, array<string, mixed>>
     */
    private function flattenSteps(array $steps): array
    {
        $out = [];
        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }
            if (($step['type'] ?? null) === 'group' && is_array($step['fields'] ?? null)) {
                foreach ($step['fields'] as $field) {
                    if (is_array($field)) {
                        $out[] = $field;
                    }
                }
            } else {
                $out[] = $step;
            }
        }

        return $out;
    }

    private function normalize(string $baseUrl): string
    {
        return rtrim(trim($baseUrl), '/');
    }
}
