<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'admin:invitar {email}';

    protected $description = 'Crea una invitación de administrador y muestra el enlace (la contraseña la elige el invitado)';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        if (User::where('email', $email)->exists()) {
            $this->error('Ya existe un usuario con ese correo.');

            return self::FAILURE;
        }
        Invitation::where('email', $email)->whereNull('accepted_at')->delete();
        [, $token] = Invitation::issue($email, User::ROLE_ADMIN, null);
        $this->info('Enlace de invitación (caduca en '.config('ipscrules.invitation_days').' días):');
        $this->line(route('invitation.show', $token));

        return self::SUCCESS;
    }
}
