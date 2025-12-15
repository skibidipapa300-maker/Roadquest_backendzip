<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $name;
    public $type;
    public $username;
    public $email;
    public $frontendUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($otp, $name, $type = 'activation', $username = null, $email = null)
    {
        $this->otp = $otp;
        $this->name = $name;
        $this->type = $type;
        $this->username = $username;
        $this->email = $email;
        $this->frontendUrl = env('FRONTEND_URL', 'https://skibidipapa300-maker.github.io/roadquest');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->type === 'reset' ? 'Password Reset Code - RoadQuest Rentals' : 'Your Verification Code - RoadQuest Rentals';

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
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
