<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['lead_id', 'user_id', 'type', 'payload', 'created_at'];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
