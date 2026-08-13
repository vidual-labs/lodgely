<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is an http(s) URL, and flags the cleartext case.
 *
 * Why this exists rather than Laravel's `url:http,https`: the fields that use
 * it (the AI provider base URL, the OpenFlow instance URL) treat an empty
 * value as "fall back to the configured default", and the built-in rule
 * rejects an empty string even alongside `nullable`. Turning that into a
 * validation error would break saving those forms with the field left blank.
 *
 * The scheme check matters because these URLs are where credentials get sent:
 * an operator who types `http://` on the OpenFlow source ships the stored
 * login email and password in cleartext on every hourly scheduler pull.
 *
 * Plain http is *not* rejected — LAN self-hosting over http is a legitimate
 * lodgely deployment and blocking it would break working installs. Use
 * {@see self::isCleartextToRemoteHost()} to warn in the UI instead.
 */
class HttpUrl implements ValidationRule
{
    /**
     * Hosts where plain http is unremarkable, because the traffic never
     * leaves the machine or the local network.
     */
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', 'host.docker.internal'];

    /** Hostname suffixes that only ever resolve on a local network. */
    private const LOCAL_SUFFIXES = ['.local', '.lan', '.internal', '.localhost'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_string($value) ? trim($value) : $value;

        // Empty means "use the default" for every field this rule guards;
        // `required` is what callers use when a value is mandatory.
        if ($value === '' || $value === null) {
            return;
        }

        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail(__('Enter a valid URL, for example https://forms.example.com.'));

            return;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail(__('The URL must start with http:// or https://.'));
        }
    }

    /**
     * True when $url is plain http pointing at something other than the local
     * machine — i.e. anything sent to it, credentials included, crosses a
     * network in cleartext. Drives the inline warnings in the settings UI.
     */
    public static function isCleartextToRemoteHost(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '' || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'http') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || in_array($host, self::LOCAL_HOSTS, true)) {
            return false;
        }

        // LAN hostnames (my-nas.local, forms.lan) are the same situation as an
        // RFC1918 address: a hop, but not one worth nagging about.
        foreach (self::LOCAL_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            // A literal IP: warn only when it is routable on the public
            // internet. Private and reserved ranges stay quiet, otherwise the
            // warning fires on every LAN install and gets clicked past.
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        // A resolvable domain name over plain http — the case worth warning on.
        return true;
    }
}
