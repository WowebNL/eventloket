<?php

namespace App\Mail;

use App\Enums\Role;
use App\Models\AdminInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AdminInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected AdminInvite $adminInvite,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        /** @var Role $role */
        $role = $this->adminInvite->role;

        return new Envelope(
            subject: __('mail/admin-invite.subject', ['role' => strtolower($role->getLabel())]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        /** @var Role $role */
        $role = $this->adminInvite->role;

        return new Content(
            markdown: 'mail.admin-invite',
            with: [
                'role' => strtolower($role->getLabel()),
                'municipality' => $this->adminInvite->municipality,
                'acceptUrl' => URL::signedRoute(
                    'admin-invites.accept',
                    ['token' => $this->adminInvite->token],
                ),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
