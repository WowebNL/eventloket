<?php

namespace App\Mail;

use App\Models\Message;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewAdviceThreadMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected Message $message,
        protected User $receiver,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        /** @var AdviceThread $adviceThread */
        $adviceThread = $this->message->thread;

        return new Envelope(
            subject: __('mail/new-advice-thread-message.subject', [
                'sender' => $this->message->user->name,
                'event' => $adviceThread->zaak->reference_data->naam_evenement,
                'municipality' => $adviceThread->zaak->municipality->name,
            ]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        /** @var AdviceThread $adviceThread */
        $adviceThread = $this->message->thread;

        $viewUrl = $adviceThread->getViewUrlForUser($this->receiver);

        $viewUrl .= "#message-{$this->message->id}";

        return new Content(
            markdown: 'mail.new-advice-thread-message',
            with: [
                'sender' => $this->message->user->name,
                'advisory' => $adviceThread->advisory->name,
                'event' => $adviceThread->zaak->reference_data->naam_evenement,
                'title' => $adviceThread->title,
                'viewUrl' => $viewUrl,
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
