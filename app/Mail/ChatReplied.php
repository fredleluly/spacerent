<?php

namespace App\Mail;

use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Mail\Mailable;

class ChatReplied extends Mailable
{
    public function __construct(public Conversation $conversation, public ChatMessage $chatMessage) {}

    public function build()
    {
        return $this->subject('Balasan baru dari SpaceRent')
            ->view('emails.chat-replied');
    }
}
