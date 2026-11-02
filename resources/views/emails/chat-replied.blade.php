<p>Halo {{ $conversation->name }},</p>
<p>Pemilik ruangan membalas pesan Anda:</p>
<blockquote>{{ $chatMessage->body }}</blockquote>
<p><a href="{{ route('buyer.chats.show', $conversation) }}">Buka percakapan</a></p>
