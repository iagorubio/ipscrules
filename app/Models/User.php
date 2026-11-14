<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_ARBITRO = 'arbitro';

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'monthly_quota'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'monthly_quota' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function askLogs(): HasMany
    {
        return $this->hasMany(AskLog::class);
    }

    /** Preguntas hechas en el mes en curso. */
    public function questionsThisMonth(): int
    {
        return $this->askLogs()->where('created_at', '>=', now()->startOfMonth())->count();
    }
}
