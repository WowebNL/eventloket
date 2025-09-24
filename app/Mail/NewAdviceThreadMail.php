<?php

namespace App\Mail;

use App\Models\Threads\AdviceThread;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewAdviceThreadMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected AdviceThread $adviceThread,
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
            subject: __('mail/new-advice-thread.subject', [
                'event' => $this->adviceThread->zaak->reference_data->naam_evenement,
                'municipality' => $this->adviceThread->zaak->municipality->name,
            ]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-advice-thread',
            with: [
                'advisory' => $this->adviceThread->advisory->name,
                'municipality' => $this->adviceThread->zaak->municipality->name,
                'event' => $this->adviceThread->zaak->reference_data->naam_evenement,
                'title' => $this->adviceThread->title,
                'viewUrl' => $this->adviceThread->getViewUrlForUser($this->receiver),
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
