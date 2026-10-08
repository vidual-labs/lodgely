<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\TwoFactor\TwoFactorAuthenticator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lost-phone escape hatch: turns 2FA off for one user so they can sign in
 * with just their password and enrol again.
 *
 * Deliberately CLI-only (server access required) rather than a button on the
 * Users page — a hijacked operator session must not be able to strip another
 * operator's second factor.
 */
class ResetUserTwoFactor extends Command
{
    protected $signature = 'lodgely:user:2fa-reset
        {email : Login email of the user}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Turn off two-factor authentication for a user who lost their authenticator and recovery codes.';

    public function handle(TwoFactorAuthenticator $twoFactor): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No user with email {$email}.");

            return self::FAILURE;
        }

        if (! $user->hasTwoFactorEnabled() && ! $user->hasPendingTwoFactorSetup()) {
            $this->info("{$email} does not have two-factor authentication enabled.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Turn off two-factor authentication for {$email}?")) {
            return self::FAILURE;
        }

        $twoFactor->disable($user);
        // Sign out any remember-me cookies too, so the reset starts clean.
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        Log::warning('lodgely.auth.two_factor_disabled', ['user_id' => $user->id, 'by' => 'cli']);

        $this->info("Two-factor authentication turned off for {$email}. They can sign in with their password and set it up again.");

        return self::SUCCESS;
    }
}
