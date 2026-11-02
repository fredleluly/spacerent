@php
    $tabs = [
        ['Pesanan Saya', route('buyer.bookings.index'), 'buyer.bookings.*'],
        ['Chat', route('buyer.chats.index'), 'buyer.chats.*'],
        ['Profil', route('buyer.profile'), 'buyer.profile'],
    ];
@endphp
<div class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-4">
        <span class="hidden self-center pr-4 text-xs font-bold uppercase tracking-wide text-slate-400 sm:block">Akun</span>
        @foreach ($tabs as [$label, $url, $pattern])
            <a href="{{ $url }}" class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold {{ request()->routeIs($pattern) ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500 hover:text-brand-600' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
