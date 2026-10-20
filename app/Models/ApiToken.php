<?php

namespace App\Models;

use App\Domain\Api\Enums\ApiTokenScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A read-only API token for another tool. Only the SHA-256 hash is stored:
 * the token is a 40-character random string, so a fast hash is enough and
 * keeps the lookup a single indexed query. The plaintext exists exactly once,
 * in the return value of {@see self::issue()}.
 */
class ApiToken extends Model
{
    /** Marks the string as a lodgely token in logs and secret scanners. */
    public const PREFIX = 'lgy_';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'token_hash',
        'token_prefix',
        'scopes',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'scopes'       => 'array',
            'last_used_at' => 'datetime',
            'revoked_at'   => 'datetime',
        ];
    }

    /**
     * Create a token and return it with its plaintext, which is not stored.
     *
     * @param  list<ApiTokenScope>  $scopes
     * @return array{0: self, 1: string}
     */
    public static function issue(int $tenantId, string $name, array $scopes, ?int $userId = null): array
    {
        $plaintext = self::PREFIX.Str::random(40);

        $token = self::create([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'name'         => $name,
            'token_hash'   => self::hash($plaintext),
            'token_prefix' => substr($plaintext, 0, 10),
            'scopes'       => array_map(static fn (ApiTokenScope $s) => $s->value, $scopes),
        ]);

        return [$token, $plaintext];
    }

    /** The active (not revoked) token for a plaintext bearer value, if any. */
    public static function findActive(string $plaintext): ?self
    {
        if ($plaintext === '') {
            return null;
        }

        return self::query()->active()->where('token_hash', self::hash($plaintext))->first();
    }

    public static function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function allows(ApiTokenScope $scope): bool
    {
        return in_array($scope->value, (array) $this->scopes, true);
    }

    /** @return list<ApiTokenScope> */
    public function grantedScopes(): array
    {
        return array_values(array_filter(array_map(
            static fn ($s) => ApiTokenScope::tryFrom((string) $s),
            (array) $this->scopes,
        )));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
