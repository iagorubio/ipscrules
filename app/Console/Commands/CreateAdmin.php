<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'admin:invitar {email} {--enviar : Enviar también la invitación por correo}';

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
        $link = route('invitation.show', $token);
        $this->line($link);

        if ($this->option('enviar')) {
            try {
                \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\InvitationMail($link, User::ROLE_ADMIN, null, now()->addDays(config('ipscrules.invitation_days'))));
                $this->info("Correo enviado a {$email}.");
            } catch (\Throwable $e) {
                $this->error('No se pudo enviar el correo: '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
