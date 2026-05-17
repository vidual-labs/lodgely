<?php

namespace App\Models;

use App\Domain\Reporting\Enums\ReportEmailSendStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReportEmailSend extends Model
{
    protected $table = 'client_report_email_sends';

    protected $fillable = [
        'tenant_id',
        'client_report_email_id',
        'schedule_id',
        'triggered_by',
        'period_from',
        'period_to',
        'recipient_user_ids',
        'ai_summary_id',
        'status',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status'             => ReportEmailSendStatus::class,
            'recipient_user_ids' => 'array',
            'period_from'        => 'date',
            'period_to'          => 'date',
            'sent_at'            => 'datetime',
        ];
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(ClientReportEmail::class, 'client_report_email_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ClientReportEmailSchedule::class, 'schedule_id');
    }

    public function aiSummary(): BelongsTo
    {
        return $this->belongsTo(AiSummary::class, 'ai_summary_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
