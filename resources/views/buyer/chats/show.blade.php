@extends('layouts.app')

@section('title', 'Chat Pemilik - SpaceRent')

@section('content')
@php $space = $conversation->space; $booking = $conversation->booking; @endphp
<div class="bg-slate-50 py-10">
    <div class="mx-auto max-w-2xl px-4">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
            <div class="border-b border-slate-100 bg-brand-900 px-5 py-4 text-white">
                <p class="font-extrabold">Chat Pemilik{{ $space?->owner ? ': '.$space->owner->name : '' }}</p>
                <p class="text-xs text-sky-200">{{ config('spacerent.reply_time') }}</p>
                @if ($space || $booking)
                    <p class="mt-2 rounded-lg bg-white/10 px-3 py-2 text-xs">
                        @if ($space)Ruangan: <a href="{{ route('spaces.show', $space) }}" class="font-bold underline">{{ $space->name }}</a>@endif
                        @if ($booking) &middot; Booking <a href="{{ route('booking.show', $booking) }}" class="font-bold underline">{{ $booking->code }}</a>@endif
                    </p>
                @endif
            </div>
            @include('chat.thread', ['mine' => 'customer', 'messagesUrl' => route('buyer.chats.messages', $conversation), 'sendUrl' => route('buyer.chats.send', $conversation)])
        </div>
        <p class="mt-3 text-center text-xs text-slate-400"><a href="{{ route('buyer.chats.index') }}" class="hover:underline">&larr; Semua percakapan</a></p>
    </div>
</div>
@endsection
