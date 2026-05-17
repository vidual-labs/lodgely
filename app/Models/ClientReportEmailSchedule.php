<?php

namespace App\Models;

use App\Domain\Reporting\Enums\ReportEmailCadence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReportEmailSchedule extends Model
{
    protected $table = 'client_report_email_schedules';

    protected $fillable = [
        'client_report_email_id',
        'cadence',
        'day_of_week',
        'day_of_month',
        'hour',
        'timezone',
        'next_run_at',
        'last_run_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cadence'      => ReportEmailCadence::class,
            'day_of_week'  => 'integer',
            'day_of_month' => 'integer',
            'hour'         => 'integer',
            'next_run_at'  => 'datetime',
            'last_run_at'  => 'datetime',
            'is_active'    => 'boolean',
        ];
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(ClientReportEmail::class, 'client_report_email_id');
    }
}
