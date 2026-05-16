<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEvent extends Model
{
    protected $table = 'ai_events';

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'ai_summary_id', 'user_id', 'type', 'payload', 'created_at'];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function aiSummary(): BelongsTo
    {
        return $this->belongsTo(AiSummary::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
