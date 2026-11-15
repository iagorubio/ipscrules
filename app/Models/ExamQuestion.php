<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamQuestion extends Model
{
    public const TYPES = ['test' => 'Test (4 opciones)', 'open' => 'Pregunta abierta'];
    public const DIFFICULTIES = ['basica' => 'Básica', 'media' => 'Media', 'dificil' => 'Difícil'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'key_points' => 'array'];
    }

    public function isTest(): bool
    {
        return $this->type === 'test';
    }
}
