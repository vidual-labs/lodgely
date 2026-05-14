<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['slug', 'name'];

    public const DEFAULT_ID = 1;
}
