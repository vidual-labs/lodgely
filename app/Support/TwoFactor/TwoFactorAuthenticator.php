<?php

namespace App\Support\TwoFactor;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * TOTP (RFC 6238) two-factor authentication plus single-use recovery codes.
 *
 * Everything happens locally: the QR code is rendered server-side as inline
 * SVG, so the secret never leaves this install (no chart/QR web service).
 * Secrets and recovery codes are stored encrypted via the User casts.
 */
class TwoFactorAuthenticator
{
    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa = new Google2FA())
    {
    }

    public function generateSecret(): string
    {
        // 32 base32 chars = 160 bits, the RFC 4226 recommended seed length.
        return $this->google2fa->generateSecretKey(32);
    }

    /** @return list<string> */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            (string) config('lodgely.brand.name', 'lodgely'),
            $user->email,
            $secret,
        );
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd()));

        $svg = $writer->writeString($this->otpauthUrl($user, $secret));

        // Drop the XML prolog so the SVG can be inlined into HTML.
        return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }

    /**
     * Check a 6-digit code against the user's secret. Returns the accepted
     * TOTP time-step, or null. Codes at or before the last accepted step are
     * rejected, so a code observed in transit cannot be replayed.
     */
    public function verifyCode(User $user, string $code, ?string $secret = null): ?int
    {
        $secret ??= $user->two_factor_secret;
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! filled($secret) || ! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        try {
            $step = $this->google2fa->verifyKeyNewer($secret, $code, (int) ($user->two_factor_last_used_step ?? 0), 1);
        } catch (Throwable) {
            return null;
        }

        return is_int($step) ? $step : null;
    }

    /** Record that a code at $step was used, closing its replay window. */
    public function markCodeUsed(User $user, int $step): void
    {
        $user->forceFill(['two_factor_last_used_step' => $step])->save();
    }

    /**
     * Consume a recovery code. Returns true and removes it from the user's
     * list when it matches; every code is single-use.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::lower(trim($code));
        if ($code === '') {
            return false;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $match = null;

        foreach ($codes as $candidate) {
            if (hash_equals((string) $candidate, $code)) {
                $match = $candidate;
            }
        }

        if ($match === null) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_values(array_filter($codes, fn ($c) => $c !== $match)),
        ])->save();

        return true;
    }

    /** Wipe every 2FA column, e.g. on disable or an admin reset. */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret'          => null,
            'two_factor_recovery_codes'  => null,
            'two_factor_confirmed_at'    => null,
            'two_factor_last_used_step'  => null,
        ])->save();
    }
}
