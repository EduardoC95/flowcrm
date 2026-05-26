<?php

namespace App\Mail;

use App\Models\CalendarEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CalendarEventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly CalendarEvent $event) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'FlowCRM reminder: '.$this->event->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.calendar-event-reminder',
        );
    }
}
