<?php

namespace App\Mail;

use App\Models\Dispute;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso al administrador: un árbitro no está de acuerdo con una respuesta. */
class DisputeMail extends Mailable
{
    public function __construct(public Dispute $dispute, public string $adminUrl)
    {
        $this->dispute->loadMissing('askLog', 'user');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Discrepancia con una respuesta · '.config('app.name'),
            replyTo: [new \Illuminate\Mail\Mailables\Address($this->dispute->user->email, $this->dispute->user->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.dispute', text: 'mail.dispute-text');
    }
}
