<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'submitted_at' => 'datetime', 'graded_at' => 'datetime',
            'score' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class)->orderBy('position');
    }

    public function inProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /** Recalcula la nota sobre 10 a partir de las respuestas ya corregidas. */
    public function recalculate(): void
    {
        $answers = $this->answers()->get();
        $max = (float) $answers->sum('max_score');
        $this->score = $max > 0 ? round($answers->sum(fn ($a) => (float) $a->score) / $max * 10, 2) : 0;
        $this->save();
    }
}
