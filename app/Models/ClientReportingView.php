<?php

namespace App\Models;

use App\Domain\Reporting\Enums\ReportColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClientReportingView extends Model
{
    protected $fillable = ['tenant_id', 'name', 'columns', 'is_live', 'created_by'];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'is_live' => 'boolean',
        ];
    }

    /** Only views that are live (visible to assigned client users). */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_live', true);
    }

    /** Returns columns as typed ReportColumn enum cases. */
    public function columnEnums(): array
    {
        return array_map(
            fn (string $v) => ReportColumn::from($v),
            $this->columns ?? []
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_reporting_view_user');
    }
}
