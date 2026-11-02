<p>Halo {{ $conversation->space?->owner?->name }},</p>
<p>{{ $conversation->name }} mengirim pesan tentang ruangan {{ $conversation->space?->name }}:</p>
<blockquote>{{ $chatMessage->body }}</blockquote>
<p><a href="{{ route('seller.chats.show', $conversation) }}">Buka percakapan</a></p>
