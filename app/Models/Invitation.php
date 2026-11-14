<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    /**
     * Crea una invitación y devuelve [modelo, token en claro]. En la base de datos solo se guarda el hash.
     */
    public static function issue(string $email, string $role, ?int $invitedBy): array
    {
        $token = Str::random(48);
        $invitation = static::create([
            'email' => mb_strtolower(trim($email)),
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $invitedBy,
            'expires_at' => now()->addDays(config('ipscrules.invitation_days')),
        ]);

        return [$invitation, $token];
    }

    public static function findValid(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
