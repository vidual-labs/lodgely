<?php

namespace App\Models;

use App\Domain\Reporting\Enums\ReportColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClientReportingView extends Model
{
    protected $fillable = ['tenant_id', 'name', 'columns', 'created_by'];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
        ];
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
