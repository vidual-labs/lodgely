<?php

namespace App\Models;

use App\Domain\Ai\Enums\AiSummaryKind;
use App\Domain\Ai\Enums\AiSummaryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiSummary extends Model
{
    protected $table = 'ai_summaries';

    protected $fillable = [
        'tenant_id',
        'kind',
        'subject_type', 'subject_id',
        'period_start', 'period_end',
        'prompt', 'response',
        'model', 'provider', 'token_usage',
        'status', 'error',
        'requested_by', 'operator_id',
        'approved_at', 'shared_at',
    ];

    protected function casts(): array
    {
        return [
            'kind'         => AiSummaryKind::class,
            'status'       => AiSummaryStatus::class,
            'token_usage'  => 'array',
            'period_start' => 'date',
            'period_end'   => 'date',
            'approved_at'  => 'datetime',
            'shared_at'    => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AiEvent::class)->latest();
    }
}
