<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional TOTP two-factor authentication for operators.
 *
 *   two_factor_secret          → base32 TOTP seed, encrypted at rest (cast on User)
 *   two_factor_recovery_codes  → JSON list of single-use codes, encrypted at rest
 *   two_factor_confirmed_at    → null while setup is pending; 2FA is only
 *                                enforced at login once this is set
 *   two_factor_last_used_step  → TOTP time-step of the last accepted code, so
 *                                the same code cannot be replayed in its window
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->unsignedBigInteger('two_factor_last_used_step')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_last_used_step',
            ]);
        });
    }
};
