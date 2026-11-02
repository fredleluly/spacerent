<?php

namespace App\Mail;

use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Mail\Mailable;

class ChatReceived extends Mailable
{
    public function __construct(public Conversation $conversation, public ChatMessage $chatMessage) {}

    public function build()
    {
        return $this->subject('Pesan baru dari calon penyewa - SpaceRent')
            ->view('emails.chat-received');
    }
}
