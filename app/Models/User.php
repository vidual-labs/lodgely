<?php

namespace App\Models;

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
        'is_active',
        'locale',
        'ui_theme',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'role'              => UserRole::class,
        ];
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
