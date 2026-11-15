@php
    $user = auth()->user();
    $links = [
        ['Jelajahi', route('home').'#ruangan'],
        ['Bantuan', route('faq')],
        ['Jadi mitra', route('register', ['role' => 'seller'])],
    ];
    $initial = $user ? mb_strtoupper(mb_substr($user->name, 0, 1)) : '';
@endphp
<header class="sticky top-0 z-40 border-b border-zinc-200 bg-white/90 backdrop-blur">
    <div class="container-page flex h-16 items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-semibold tracking-tight">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white"><i data-lucide="building-2" class="h-5 w-5"></i></span>
            SpaceRent
        </a>

        <nav class="hidden items-center gap-1 md:flex" aria-label="Utama">
            @foreach ($links as [$label, $url])
                <a href="{{ $url }}" class="rounded-xl px-3.5 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            @guest
                <a href="{{ route('login') }}" class="btn btn-ghost hidden sm:inline-flex">Masuk</a>
                <a href="{{ route('register') }}" class="btn btn-primary">Daftar</a>
            @else
                @if ($user->isBuyer())
                    <a href="{{ route('buyer.favorites') }}" class="btn btn-ghost hidden !px-3 sm:inline-flex" aria-label="Favorit"><i data-lucide="heart" class="h-5 w-5"></i></a>
                    <a href="{{ route('buyer.bookings.index') }}" class="btn btn-ghost hidden sm:inline-flex">Pesanan</a>
                @else
                    <a href="{{ $user->homeRoute() }}" class="btn btn-outline hidden sm:inline-flex">{{ $user->isAdmin() ? 'Panel admin' : 'Panel penjual' }}</a>
                @endif
                <details class="relative" data-menu>
                    <summary class="flex h-10 cursor-pointer list-none items-center gap-2 rounded-full border border-zinc-300 py-1 pl-3 pr-1 transition hover:shadow-soft [&::-webkit-details-marker]:hidden">
                        <i data-lucide="menu" class="h-4 w-4 text-zinc-700"></i>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-900 text-sm font-semibold text-white">{{ $initial }}</span>
                    </summary>
                    <div class="absolute right-0 mt-2 w-60 overflow-hidden rounded-2xl border border-zinc-200 bg-white py-2 shadow-lift">
                        <p class="truncate px-4 pb-2 pt-1 text-sm font-semibold">{{ $user->name }}</p>
                        <p class="truncate px-4 pb-2 text-xs text-zinc-600">{{ $user->email }}</p>
                        <div class="border-t border-zinc-100 pt-1">
                            @if ($user->isBuyer())
                                <a class="block px-4 py-2 text-sm hover:bg-zinc-50" href="{{ route('buyer.bookings.index') }}">Pesanan saya</a>
                                <a class="block px-4 py-2 text-sm hover:bg-zinc-50" href="{{ route('buyer.favorites') }}">Favorit</a>
                                <a class="block px-4 py-2 text-sm hover:bg-zinc-50" href="{{ route('buyer.chats.index') }}">Pesan</a>
                                <a class="block px-4 py-2 text-sm hover:bg-zinc-50" href="{{ route('buyer.profile') }}">Profil</a>
                            @else
                                <a class="block px-4 py-2 text-sm hover:bg-zinc-50" href="{{ $user->homeRoute() }}">Buka panel</a>
                            @endif
                        </div>
                        <form method="POST" action="{{ $user->isAdmin() ? route('admin.logout') : route('logout') }}" class="border-t border-zinc-100 pt-1">
                            @csrf
                            <button class="block w-full px-4 py-2 text-left text-sm hover:bg-zinc-50">Keluar</button>
                        </form>
                    </div>
                </details>
            @endguest

            <button type="button" class="btn btn-ghost !px-3 md:hidden" data-toggle="#mobile-menu" aria-expanded="false" aria-controls="mobile-menu" aria-label="Menu"><i data-lucide="menu" class="h-5 w-5"></i></button>
        </div>
    </div>

    <div id="mobile-menu" hidden class="border-t border-zinc-200 bg-white md:hidden">
        <nav class="container-page flex flex-col py-2" aria-label="Menu seluler">
            @foreach ($links as [$label, $url])
                <a href="{{ $url }}" class="rounded-xl px-2 py-3 text-sm font-medium">{{ $label }}</a>
            @endforeach
            @guest
                <a href="{{ route('login') }}" class="rounded-xl px-2 py-3 text-sm font-medium">Masuk</a>
            @else
                @if ($user->isBuyer())
                    <a href="{{ route('buyer.bookings.index') }}" class="rounded-xl px-2 py-3 text-sm font-medium">Pesanan saya</a>
                    <a href="{{ route('buyer.favorites') }}" class="rounded-xl px-2 py-3 text-sm font-medium">Favorit</a>
                @endif
            @endguest
        </nav>
    </div>
</header>
