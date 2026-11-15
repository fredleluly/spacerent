<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email umum siklus booking: dibuat, disetujui, dibatalkan, refund, pengingat, minta ulasan, dll. */
class BookingNotice extends Mailable
{
    /** @param list<string> $lines */
    public function __construct(
        public Booking $booking,
        public string $title,
        public array $lines,
        public ?string $actionLabel = 'Lihat Booking',
        public ?string $actionUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title.' - Booking '.$this->booking->code);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.booking-notice');
    }
}
