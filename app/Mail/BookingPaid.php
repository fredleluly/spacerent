<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingPaid extends Mailable
{
    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pembayaran Berhasil - Booking '.$this->booking->code);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.booking-paid');
    }
}
