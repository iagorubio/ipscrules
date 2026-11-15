<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['max_score' => 'float', 'score' => 'float'];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'question_id');
    }

    public function isCorrect(): bool
    {
        return $this->score !== null && $this->score >= $this->max_score - 0.001;
    }
}
