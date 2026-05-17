<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientReportEmail extends Model
{
    protected $table = 'client_report_emails';

    protected $fillable = [
        'tenant_id',
        'name',
        'client_reporting_view_id',
        'intro_markdown',
        'include_kpi_strip',
        'include_metrics_table',
        'include_ai_summary',
        'period_months',
        'subject_template',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'include_kpi_strip'     => 'boolean',
            'include_metrics_table' => 'boolean',
            'include_ai_summary'    => 'boolean',
            'is_active'             => 'boolean',
            'period_months'         => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reportingView(): BelongsTo
    {
        return $this->belongsTo(ClientReportingView::class, 'client_reporting_view_id');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_report_email_recipients')
            ->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ClientReportEmailSchedule::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(ClientReportEmailSend::class);
    }
}
