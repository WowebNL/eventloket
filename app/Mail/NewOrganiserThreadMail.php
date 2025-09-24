<?php

namespace App\Mail;

use App\Models\Threads\OrganiserThread;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOrganiserThreadMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected OrganiserThread $organiserThread,
        protected User $receiver,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail/new-organiser-thread.subject', [
                'event' => $this->organiserThread->zaak->reference_data->naam_evenement,
                'municipality' => $this->organiserThread->zaak->municipality->name,
            ]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-organiser-thread',
            with: [
                'organisation' => $this->organiserThread->zaak->organisation->name,
                'municipality' => $this->organiserThread->zaak->municipality->name,
                'event' => $this->organiserThread->zaak->reference_data->naam_evenement,
                'title' => $this->organiserThread->title,
                'viewUrl' => $this->organiserThread->getViewUrlForUser($this->receiver),
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
