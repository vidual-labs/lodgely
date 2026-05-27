<?php

namespace App\Domain\Leads\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared inbox filter / sort semantics.
 *
 * Used by the inbox Livewire trait and the lead export controller so the
 * two surfaces always agree on what the URL query params mean.
 */
class LeadFilter
{
    /**
     * @param  array{search?: string|null, status?: string|null, priority?: string|null, source?: string|null, client?: string|null}  $state
     */
    public function apply(Builder $base, array $state): Builder
    {
        return (clone $base)
            ->search($state['search'] ?? '')
            ->when($state['status'] ?? '', fn ($q, $v) => $q->where('status', $v))
            ->when($state['priority'] ?? '', fn ($q, $v) => $q->where('priority', $v))
            ->when($state['source'] ?? '', fn ($q, $v) => $q->where('source', $v))
            ->when($state['client'] ?? '', fn ($q, $v) => $q->whereRaw('LOWER(client_name) = ?', [mb_strtolower((string) $v)]));
    }

    /** @return array{0:string, 1:string} */
    public function sortBy(string $sort): array
    {
        return match ($sort) {
            'created_asc' => ['created_at', 'asc'],
            'priority_desc' => ['priority', 'desc'],
            'priority_asc' => ['priority', 'asc'],
            'name_asc' => ['full_name', 'asc'],
            'name_desc' => ['full_name', 'desc'],
            'status_asc' => ['status', 'asc'],
            'status_desc' => ['status', 'desc'],
            default => ['created_at', 'desc'],
        };
    }

    public static function sortableColumns(): array
    {
        return [
            'received' => ['asc' => 'created_asc', 'desc' => 'created_desc'],
            'name'     => ['asc' => 'name_asc', 'desc' => 'name_desc'],
            'status'   => ['asc' => 'status_asc', 'desc' => 'status_desc'],
            'priority' => ['asc' => 'priority_asc', 'desc' => 'priority_desc'],
        ];
    }
}
