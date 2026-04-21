<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contactMessage, public Site $site) {}

    public function envelope(): Envelope
    {
        $fromEmail = filled($this->site->footer_content['email'] ?? null)
            ? $this->site->footer_content['email']
            : config('mail.from.address');
        $fromName = $this->site->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: "[{$this->site->name}] 新的聯絡訊息 — {$this->contactMessage->name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-message');
    }
}
