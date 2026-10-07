<?php

namespace App\Models;

use App\Domain\Leads\Enums\ClientType;
use App\Domain\Leads\Enums\UserRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'client_type',
        'is_active',
        'locale',
        'ui_theme',
        'inbox_columns',
        'inbox_filters',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => UserRole::class,
            'client_type' => ClientType::class,
            'inbox_columns' => 'array',
            'inbox_filters' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_step' => 'integer',
        ];
    }

    /**
     * True once 2FA setup has been confirmed with a valid code. Enforced at
     * login regardless of role: enrolment is operator-only, but demoting an
     * enrolled operator to client must not silently drop their second factor.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    /** Setup started (secret generated) but not yet confirmed with a code. */
    public function hasPendingTwoFactorSetup(): bool
    {
        return $this->two_factor_confirmed_at === null && filled($this->two_factor_secret);
    }

    /** Whether this user may enrol in 2FA from their profile. */
    public function canEnableTwoFactor(): bool
    {
        return $this->isOperator();
    }

    public function leadScopes(): HasMany
    {
        return $this->hasMany(UserLeadScope::class);
    }

    public function savedFilters(): HasMany
    {
        return $this->hasMany(SavedFilter::class);
    }

    public function reportingViews(): BelongsToMany
    {
        return $this->belongsToMany(ClientReportingView::class, 'client_reporting_view_user');
    }

    public function isOperator(): bool
    {
        return $this->role === UserRole::Operator;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    /** Returns the list of client_name values this user is allowed to see, or null = unrestricted. */
    public function allowedClientNames(): ?array
    {
        if ($this->isOperator()) {
            return null;
        }

        return $this->leadScopes()->pluck('client_name')->all();
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
            $letters = array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));

            return mb_strtoupper(implode('', $letters)) ?: 'L';
        });
    }
}
