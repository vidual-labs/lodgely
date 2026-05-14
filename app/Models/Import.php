<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'source', 'label', 'reference',
        'rows_total', 'rows_imported', 'rows_duplicate', 'rows_invalid',
        'meta', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'meta'        => 'array',
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
