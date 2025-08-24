<?php

namespace App\Mail;

use App\Enums\Role;
use App\Models\MunicipalityInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class MunicipalityInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected MunicipalityInvite $municipalityInvite,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        /** @var Role $role */
        $role = $this->municipalityInvite->role;

        return new Envelope(
            subject: __('mail/municipality-invite.subject', ['role' => strtolower($role->getLabel())]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        /** @var Role $role */
        $role = $this->municipalityInvite->role;

        return new Content(
            markdown: 'mail.municipality-invite',
            with: [
                'role' => strtolower($role->getLabel()),
                'municipalities' => $this->municipalityInvite->municipalities,
                'acceptUrl' => URL::signedRoute(
                    'municipality-invites.accept',
                    ['token' => $this->municipalityInvite->token],
                ),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
