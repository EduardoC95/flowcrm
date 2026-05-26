<?php

namespace App\Mail;

use App\Models\Deal;
use App\Models\FollowUpTemplate;
use App\Models\User;
use App\Services\FollowUpService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DealFollowUpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $renderedSubject;

    public string $renderedBody;

    public function __construct(
        public readonly Deal $deal,
        public readonly FollowUpTemplate $template,
        public readonly ?User $sender = null,
    ) {
        $rendered = app(FollowUpService::class)->renderTemplate($template, $deal, $sender);
        $this->renderedSubject = $rendered['subject'];
        $this->renderedBody = $rendered['body'];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.deal-follow-up',
            with: [
                'body' => $this->renderedBody,
            ],
        );
    }
}
