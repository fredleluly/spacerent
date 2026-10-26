@extends('admin.layout')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
        ['Total Ruangan', $stats['spaces'], 'building-2', 'text-brand-600 bg-brand-50'],
        ['Booking Menunggu', $stats['pending'], 'clock', 'text-amber-600 bg-amber-50'],
        ['Total Booking', $stats['bookings'], 'calendar-check', 'text-emerald-600 bg-emerald-50'],
        ['Pendapatan (Dikonfirmasi)', 'Rp '.number_format($stats['revenue'], 0, ',', '.'), 'wallet', 'text-violet-600 bg-violet-50'],
    ] as [$label, $value, $icon, $color])
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl {{ $color }}"><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
            <div class="min-w-0"><p class="text-xs text-slate-500">{{ $label }}</p><p class="truncate text-xl font-extrabold">{{ $value }}</p></div>
        </div>
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1.6fr_1fr]">
    <section class="rounded-xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="font-bold">Booking Terbaru</h2>
            <a href="{{ route('admin.bookings.index') }}" class="text-xs font-bold text-brand-600">Lihat semua</a>
        </div>
        @include('admin.partials.booking-table', ['bookings' => $latestBookings])
    </section>

    <section class="rounded-xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="font-bold">Pesan Terbaru</h2>
            <a href="{{ route('admin.messages.index') }}" class="text-xs font-bold text-brand-600">Lihat semua</a>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse ($latestMessages as $m)
                <li><a href="{{ route('admin.messages.show', $m) }}" class="block px-5 py-3 hover:bg-slate-50">
                    <p class="text-sm font-semibold">{{ $m->subject }}</p>
                    <p class="text-xs text-slate-500">{{ $m->name }} &middot; {{ $m->created_at->diffForHumans() }}</p>
                </a></li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-400">Belum ada pesan.</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection
