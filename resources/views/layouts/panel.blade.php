<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel') - SpaceRent {{ $panel === 'admin' ? 'Admin' : 'Penjual' }}</title>
    <meta name="robots" content="noindex,nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-[100dvh] bg-zinc-50 text-zinc-900">
@php
    $isAdmin = $panel === 'admin';
    $uid = auth()->id();
    $mine = fn ($q) => $q->whereHas('space', fn ($s) => $s->where('user_id', $uid));
    if ($isAdmin) {
        $menu = [
            ['admin.dashboard', 'Dashboard', 'layout-dashboard', 'admin.dashboard', 0],
            ['admin.spaces.index', 'Ruangan', 'building-2', 'admin.spaces.*', 0],
            ['admin.bookings.index', 'Booking', 'calendar-check', 'admin.bookings.*', \App\Models\Booking::where('refund_status', 'pending')->count()],
            ['admin.reviews.index', 'Ulasan', 'star', 'admin.reviews.*', \App\Models\Review::where('reported_count', '>', 0)->where('status', 'published')->count()],
            ['admin.payouts.index', 'Penarikan', 'banknote', 'admin.payouts.*', \App\Models\Payout::where('status', 'requested')->count()],
            ['admin.messages.index', 'Pesan masuk', 'mail', 'admin.messages.*', 0],
            ['admin.chats.index', 'Chat', 'message-circle', 'admin.chats.*', (int) \App\Models\Conversation::sum('unread_admin')],
            ['admin.users.index', 'Pengguna', 'users', 'admin.users.*', 0],
            ['admin.chatbot.edit', 'Chatbot AI', 'sparkles', 'admin.chatbot.*', 0],
        ];
    } else {
        $menu = [
            ['seller.dashboard', 'Dashboard', 'layout-dashboard', 'seller.dashboard', 0],
            ['seller.calendar', 'Kalender', 'calendar-days', 'seller.calendar', 0],
            ['seller.spaces.index', 'Ruangan', 'building-2', 'seller.spaces.*', 0],
            ['seller.bookings.index', 'Booking', 'calendar-check', 'seller.bookings.*', \App\Models\Booking::where('status', 'awaiting_approval')->where('approval_deadline_at', '>', now())->whereHas('space', fn ($s) => $s->where('user_id', $uid))->count()],
            ['seller.reviews.index', 'Ulasan', 'star', 'seller.reviews.*', \App\Models\Review::whereNull('reply')->whereHas('space', fn ($s) => $s->where('user_id', $uid))->count()],
            ['seller.payouts.index', 'Pendapatan', 'wallet', 'seller.payouts.*', 0],
            ['seller.chats.index', 'Chat', 'message-circle', 'seller.chats.*', (int) \App\Models\Conversation::whereHas('space', fn ($s) => $s->where('user_id', $uid))->sum('unread_admin')],
        ];
    }
@endphp
<div class="flex min-h-[100dvh]">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-zinc-200 bg-white lg:flex">
        <a href="{{ route($isAdmin ? 'admin.dashboard' : 'seller.dashboard') }}" class="flex h-16 items-center gap-2 border-b border-zinc-200 px-5 font-semibold tracking-tight">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white"><i data-lucide="building-2" class="h-4 w-4"></i></span>
            SpaceRent <span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-[11px] font-medium text-zinc-700">{{ $isAdmin ? 'Admin' : 'Penjual' }}</span>
        </a>
        <nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="Panel">
            @foreach ($menu as [$route, $label, $icon, $pattern, $badge])
                <a href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs($pattern) ? 'bg-zinc-900 text-white' : 'text-zinc-700 hover:bg-zinc-100' }}">
                    <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>{{ $label }}
                    @if ($badge)<span class="ml-auto rounded-full bg-brand-600 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $badge }}</span>@endif
                </a>
            @endforeach
        </nav>
        <div class="space-y-1 border-t border-zinc-200 p-3 text-sm">
            <a href="{{ route('home') }}" target="_blank" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-zinc-700 hover:bg-zinc-100"><i data-lucide="external-link" class="h-4 w-4"></i>Lihat situs</a>
            <form method="POST" action="{{ route($isAdmin ? 'admin.logout' : 'logout') }}">@csrf
                <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 font-medium text-zinc-700 hover:bg-zinc-100"><i data-lucide="log-out" class="h-4 w-4"></i>Keluar</button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/90 backdrop-blur">
            <div class="flex h-16 items-center justify-between px-4 lg:px-8">
                <h1 class="text-lg font-semibold tracking-tight">@yield('heading')</h1>
                <span class="text-sm text-zinc-600">{{ auth()->user()->name }}</span>
            </div>
            <nav class="flex gap-1 overflow-x-auto border-t border-zinc-100 px-3 py-2 lg:hidden" aria-label="Menu panel">
                @foreach ($menu as [$route, $label, $icon, $pattern, $badge])
                    <a href="{{ route($route) }}" class="chip !px-3 !py-1.5 !text-xs" @if (request()->routeIs($pattern)) aria-current="true" @endif>{{ $label }}@if ($badge) ({{ $badge }})@endif</a>
                @endforeach
            </nav>
        </header>

        <main class="flex-1 p-4 lg:p-8">
            @foreach (['success' => 'border-emerald-200 bg-emerald-50 text-emerald-900', 'error' => 'border-rose-200 bg-rose-50 text-rose-900'] as $type => $cls)
                @if (session($type))
                    <div data-flash="{{ $type }}" class="mb-5 flex items-start gap-2 rounded-xl border px-4 py-3 text-sm {{ $cls }}" role="status"><span class="flex-1">{{ session($type) }}</span><button type="button" data-dismiss class="opacity-70 hover:opacity-100" aria-label="Tutup"><i data-lucide="x" class="h-4 w-4"></i></button></div>
                @endif
            @endforeach
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
