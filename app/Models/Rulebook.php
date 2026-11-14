<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rulebook extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'imported_at' => 'datetime'];
    }
}
