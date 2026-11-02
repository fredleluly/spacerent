@extends('layouts.app')

@section('title', 'Chat Saya - SpaceRent')

@section('content')
<div class="bg-slate-50 py-10">
    <div class="mx-auto max-w-2xl px-4">
        <h1 class="mb-4 text-xl font-extrabold">Chat Saya</h1>
        <div class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm">
            @forelse ($conversations as $c)
                <a href="{{ route('buyer.chats.show', $c) }}" class="flex items-start gap-4 px-5 py-4 hover:bg-slate-50">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold">{{ $c->space?->name ?? 'Ruangan' }}
                            <span class="ml-1 text-xs font-normal text-slate-400">{{ $c->space?->owner?->name }}</span></p>
                        @if ($c->booking)<p class="text-xs text-slate-500">Booking {{ $c->booking->code }}</p>@endif
                        <p class="mt-1 truncate text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($previews[$c->last_body_id] ?? '', 90) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-400">{{ $c->last_message_at?->diffForHumans() }}</p>
                        @if ($c->unread_customer)
                            <span class="mt-1 inline-block rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $c->unread_customer }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <p class="p-8 text-center text-sm text-slate-500">Belum ada percakapan. Mulai dari halaman ruangan.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $conversations->links() }}</div>
    </div>
</div>
@endsection
