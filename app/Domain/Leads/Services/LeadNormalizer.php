<?php

namespace App\Domain\Leads\Services;

/**
 * Pure, side-effect free normalization helpers. The Ingestor always runs
 * incoming data through these before persisting, so DuplicateDetector and
 * downstream queries can rely on stable shapes.
 */
class LeadNormalizer
{
    public function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = trim($email);
        if ($email === '') {
            return null;
        }

        // Lowercase + strip Gmail/Outlook +tag aliases for duplicate detection.
        $email = mb_strtolower($email);
        if (str_contains($email, '@')) {
            [$local, $domain] = explode('@', $email, 2);
            $local = explode('+', $local, 2)[0];
            $email = $local.'@'.$domain;
        }

        return $email;
    }

    /**
     * Reduce a phone number to digits only for fuzzy matching. We deliberately
     * do NOT canonicalize country codes — that would need libphonenumber and
     * isn't worth the dependency for an MVP. Operators see the original phone.
     */
    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // Anything shorter than 5 digits is too noisy to be useful for matching.
        return mb_strlen($digits) >= 5 ? $digits : null;
    }

    public function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }
}
