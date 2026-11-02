@php
    $wa = config('spacerent.whatsapp');
    $waLink = "https://wa.me/{$wa}?text=".rawurlencode('Halo SpaceRent, saya ingin bertanya tentang sewa ruangan.');
    $nav = [
        ['Meeting Room', route('home', ['category' => 'meeting-room']).'#ruangan'],
        ['Private Office', route('home', ['category' => 'private-office']).'#ruangan'],
        ['Coworking', route('home', ['category' => 'coworking']).'#ruangan'],
        ['Event Space', route('home', ['category' => 'event-space']).'#ruangan'],
        ['Syarat & Ketentuan', route('terms')],
        ['FAQ', route('faq')],
        ['Hubungi Kami', route('contact')],
    ];
@endphp

{{-- Top bar --}}
<div class="bg-brand-900 text-white text-xs">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-2">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
            <span class="inline-flex items-center gap-1.5"><i data-lucide="map-pin" class="h-3.5 w-3.5 text-sky-400"></i>Jakarta &amp; Jabodetabek Area</span>
            <span class="hidden items-center gap-1.5 sm:inline-flex"><i data-lucide="clock" class="h-3.5 w-3.5 text-emerald-400"></i>Buka 24/7 (Reservasi &amp; Layanan)</span>
            <span class="hidden rounded border border-amber-400/60 bg-amber-400/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-300 md:inline">LKPP / E-Katalog Ready</span>
        </div>
        <a href="tel:{{ preg_replace('/\D/', '', config('spacerent.hotline')) }}" class="inline-flex items-center gap-1.5 font-semibold text-emerald-300 hover:text-emerald-200">
            <i data-lucide="phone-call" class="h-3.5 w-3.5"></i>
            <span class="hidden sm:inline">Hotline WA 24 Jam:</span> {{ config('spacerent.hotline') }}
        </a>
    </div>
</div>

{{-- Navbar --}}
<header class="sticky top-0 z-40 border-b border-slate-100 bg-white/95 shadow-sm backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow-md shadow-brand-600/30">
                <i data-lucide="building" class="h-5 w-5"></i>
            </span>
            <span class="leading-tight">
                <span class="block text-lg font-extrabold tracking-tight text-brand-900">Space<span class="text-brand-600">Rent</span></span>
                <span class="block text-[10px] font-semibold uppercase tracking-widest text-slate-400">Venue &amp; Office Hub</span>
            </span>
        </a>

        <nav class="hidden items-center gap-6 text-sm font-medium text-slate-600 lg:flex">
            @foreach ($nav as [$label, $url])
                <a href="{{ $url }}" class="transition hover:text-brand-600">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ $waLink }}" target="_blank" rel="noopener" class="hidden items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow transition hover:bg-emerald-700 sm:inline-flex">
                <i data-lucide="message-square" class="h-4 w-4"></i> WhatsApp CS
            </a>
            @auth
                @if (auth()->user()->isBuyer())
                    <a href="{{ route('buyer.bookings.index') }}" class="hidden rounded-lg px-3 py-2 text-xs font-bold text-slate-700 hover:text-brand-600 lg:inline-block">Pesanan Saya</a>
                    <a href="{{ route('buyer.chats.index') }}" class="hidden rounded-lg px-3 py-2 text-xs font-bold text-slate-700 hover:text-brand-600 lg:inline-block">Chat</a>
                @elseif (auth()->user()->isSeller())
                    <a href="{{ route('seller.dashboard') }}" class="hidden rounded-lg px-3 py-2 text-xs font-bold text-slate-700 hover:text-brand-600 lg:inline-block">Dashboard Penjual</a>
                @else
                    <a href="{{ route('admin.dashboard') }}" class="hidden rounded-lg px-3 py-2 text-xs font-bold text-slate-700 hover:text-brand-600 lg:inline-block">Admin</a>
                @endif
                <span class="hidden lg:inline-block"><form method="POST" action="{{ route('logout') }}" class="inline">@csrf<button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:border-brand-500">Keluar</button></form></span>
            @else
                <a href="{{ route('login') }}" class="hidden rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:border-brand-500 sm:inline-block">Masuk</a>
                <a href="{{ route('register') }}" class="hidden rounded-lg bg-brand-600 px-3 py-2 text-xs font-bold text-white hover:bg-brand-700 sm:inline-block">Daftar</a>
            @endauth
            <button type="button" id="menu-toggle" aria-label="Buka menu" class="rounded-lg border border-slate-200 p-2 lg:hidden">
                <i data-lucide="menu" class="h-5 w-5"></i>
            </button>
        </div>
    </div>

    <div id="mobile-menu" class="hidden border-t border-slate-100 bg-white lg:hidden">
        <div class="mx-auto flex max-w-6xl flex-col px-4 py-2">
            @foreach ($nav as [$label, $url])
                <a href="{{ $url }}" class="border-b border-slate-50 py-3 text-sm font-medium text-slate-700">{{ $label }}</a>
            @endforeach
            @auth
                @if (auth()->user()->isBuyer())
                    <a href="{{ route('buyer.bookings.index') }}" class="border-b border-slate-50 py-3 text-sm font-bold text-brand-600">Pesanan Saya</a>
                    <a href="{{ route('buyer.chats.index') }}" class="border-b border-slate-50 py-3 text-sm font-bold text-brand-600">Chat</a>
                @elseif (auth()->user()->isSeller())
                    <a href="{{ route('seller.dashboard') }}" class="border-b border-slate-50 py-3 text-sm font-bold text-brand-600">Dashboard Penjual</a>
                @else
                    <a href="{{ route('admin.dashboard') }}" class="border-b border-slate-50 py-3 text-sm font-bold text-brand-600">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="border-b border-slate-50">@csrf<button type="submit" class="w-full py-3 text-left text-sm font-medium text-slate-700">Keluar</button></form>
            @else
                <a href="{{ route('login') }}" class="border-b border-slate-50 py-3 text-sm font-bold text-brand-600">Masuk</a>
                <a href="{{ route('register') }}" class="border-b border-slate-50 py-3 text-sm font-bold text-brand-600">Daftar</a>
            @endauth
            <a href="{{ $waLink }}" target="_blank" rel="noopener" class="my-3 inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">
                <i data-lucide="message-square" class="h-4 w-4"></i> WhatsApp CS
            </a>
        </div>
    </div>
</header>

@push('scripts')
<script>
    document.getElementById('menu-toggle')?.addEventListener('click', () => {
        document.getElementById('mobile-menu').classList.toggle('hidden');
    });
</script>
@endpush
