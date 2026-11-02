@extends('seller.layout')

@section('title', 'Chat '.($conversation->user?->name ?? $conversation->name))
@section('heading', 'Chat: '.($conversation->user?->name ?? $conversation->name))

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="overflow-hidden rounded-xl bg-white shadow-sm lg:col-span-2">
        @include('chat.thread', ['mine' => 'admin', 'height' => 'h-[28rem]', 'messagesUrl' => route('seller.chats.messages', $conversation), 'sendUrl' => route('seller.chats.reply', $conversation)])
    </div>
    <div class="space-y-4">
        @include('chat.context', ['bookingRoute' => 'seller.bookings.show', 'toggleRoute' => 'seller.chats.toggle', 'indexRoute' => 'seller.chats.index'])
    </div>
</div>
@endsection
