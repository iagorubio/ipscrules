<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    public const STATUSES = ['open' => 'Abierta', 'confirmed' => 'Confirmada: la IA se equivocó', 'rejected' => 'Descartada: la respuesta era correcta'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['mailed_at' => 'datetime'];
    }

    public function askLog(): BelongsTo
    {
        return $this->belongsTo(AskLog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
