@php
    $tabs = [
        ['Pesanan', route('buyer.bookings.index'), 'buyer.bookings.*'],
        ['Favorit', route('buyer.favorites'), 'buyer.favorites'],
        ['Pesan', route('buyer.chats.index'), 'buyer.chats.*'],
        ['Profil', route('buyer.profile'), 'buyer.profile'],
    ];
@endphp
<nav class="border-b border-zinc-200 bg-white" aria-label="Akun">
    <div class="container-page flex gap-1 overflow-x-auto">
        @foreach ($tabs as [$label, $url, $pattern])
            <a href="{{ $url }}" @if (request()->routeIs($pattern)) aria-current="page" @endif class="whitespace-nowrap border-b-2 px-4 py-3.5 text-sm font-medium {{ request()->routeIs($pattern) ? 'border-zinc-900 text-zinc-900' : 'border-transparent text-zinc-600 hover:text-zinc-900' }}">{{ $label }}</a>
        @endforeach
    </div>
</nav>
