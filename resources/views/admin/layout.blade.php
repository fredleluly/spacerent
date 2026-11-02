<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') - SpaceRent Admin</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                fontFamily: { sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                colors: { brand: { 50: '#eff8ff', 100: '#dbeefe', 500: '#0b7bc1', 600: '#0369a1', 700: '#075985', 900: '#0b2a43' } },
            } },
        };
    </script>
    <script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        select { -webkit-appearance: none; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23334155' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right .75rem center; padding-right: 2.25rem; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
@php
    $menu = [
        ['admin.dashboard', 'Dashboard', 'layout-dashboard', 'admin.dashboard'],
        ['admin.spaces.index', 'Ruangan', 'building-2', 'admin.spaces.*'],
        ['admin.bookings.index', 'Booking', 'calendar-check', 'admin.bookings.*'],
        ['admin.messages.index', 'Pesan Masuk', 'mail', 'admin.messages.*'],
        ['admin.chats.index', 'Chat', 'message-circle', 'admin.chats.*'],
        ['admin.users.index', 'Pengguna', 'users', 'admin.users.*'],
    ];
    $chatUnread = (int) \App\Models\Conversation::sum('unread_admin');
    $pendingCount = \App\Models\Booking::where('status', 'pending')->count();
@endphp
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col bg-brand-900 text-slate-300 lg:flex">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 px-5 py-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-white"><i data-lucide="building" class="h-5 w-5"></i></span>
            <span class="font-extrabold text-white">Space<span class="text-sky-400">Rent</span> <span class="text-[10px] font-semibold uppercase text-slate-400">Admin</span></span>
        </a>
        <nav class="mt-2 flex-1 space-y-1 px-3">
            @foreach ($menu as [$route, $label, $icon, $pattern])
                <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs($pattern) ? 'bg-brand-600 text-white' : 'hover:bg-white/10 hover:text-white' }}">
                    <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>{{ $label }}
                    @if ($route === 'admin.bookings.index' && $pendingCount)
                        <span class="ml-auto rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-bold text-slate-900">{{ $pendingCount }}</span>
                    @endif
                    @if ($route === 'admin.chats.index' && $chatUnread)
                        <span class="ml-auto rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $chatUnread }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
        <div class="border-t border-white/10 p-3 text-sm">
            <a href="{{ route('home') }}" target="_blank" class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-semibold hover:bg-white/10 hover:text-white"><i data-lucide="external-link" class="h-4 w-4"></i>Lihat Website</a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 font-semibold hover:bg-white/10 hover:text-white"><i data-lucide="log-out" class="h-4 w-4"></i>Keluar</button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-60">
        <header class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:px-8">
            <div class="flex items-center gap-3">
                <button type="button" id="side-toggle" class="rounded-lg border border-slate-200 p-2 lg:hidden" aria-label="Menu"><i data-lucide="menu" class="h-5 w-5"></i></button>
                <h1 class="text-lg font-extrabold">@yield('heading')</h1>
            </div>
            <span class="text-sm text-slate-500">{{ auth()->user()->name }}</span>
        </header>

        <main class="flex-1 p-4 lg:p-8">
            @if (session('success'))
                <div class="mb-5 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"><i data-lucide="circle-check" class="h-4 w-4"></i>{{ session('success') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script>
    lucide.createIcons();
    document.getElementById('side-toggle')?.addEventListener('click', () => {
        const s = document.getElementById('sidebar');
        s.classList.toggle('hidden'); s.classList.toggle('flex');
    });
    document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => {
        if (!confirm(f.dataset.confirm)) e.preventDefault();
    }));
</script>
</body>
</html>
