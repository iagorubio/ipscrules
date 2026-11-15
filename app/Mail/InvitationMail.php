<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Invitación para crear una cuenta; contiene el enlace personal de un solo uso. */
class InvitationMail extends Mailable
{
    public function __construct(
        public string $link,
        public string $role,
        public ?string $inviter,
        public CarbonInterface $expires,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invitación a '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invitation', text: 'mail.invitation-text', with: ['roleLabel' => $this->roleLabel()]);
    }

    public function roleLabel(): string
    {
        return $this->role === 'admin' ? 'administrador' : 'árbitro';
    }
}
