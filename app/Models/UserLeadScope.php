<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLeadScope extends Model
{
    protected $fillable = ['user_id', 'client_name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
