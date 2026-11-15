@extends('layouts.app')

@section('title', 'Pesanan saya - SpaceRent')

@section('content')
@include('buyer._nav')
<div class="container-page py-8">
    <h1 class="text-2xl font-semibold tracking-tight">Pesanan saya</h1>

    <div class="mt-5 flex gap-2 overflow-x-auto pb-1" role="tablist">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('buyer.bookings.index', ['status' => $key]) }}" class="chip" @if ($tab === $key) aria-current="true" @endif>
                {{ $label }}
                <span class="rounded-full px-1.5 text-xs {{ $tab === $key ? 'bg-white/20' : 'bg-zinc-100 text-zinc-700' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    @if ($bookings->isEmpty())
        <div class="mx-auto mt-12 max-w-md rounded-2xl border border-dashed border-zinc-300 p-10 text-center">
            <i data-lucide="calendar-x" class="mx-auto h-10 w-10 text-zinc-500"></i>
            <h2 class="mt-4 text-lg font-semibold">Belum ada pesanan di sini</h2>
            <p class="mt-1 text-sm text-zinc-600">Jelajahi ruangan dan pilih jadwal yang cocok.</p>
            <a href="{{ route('home') }}#ruangan" class="btn btn-primary mt-5">Cari ruangan</a>
        </div>
    @else
        <ul class="mt-6 space-y-4">
            @foreach ($bookings as $b)
                <li class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                    <a href="{{ route('booking.show', $b) }}" class="block h-28 w-full shrink-0 overflow-hidden rounded-xl bg-zinc-100 sm:h-24 sm:w-36">
                        <img src="{{ $b->space->image_url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                    </a>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-status-badge :booking="$b" />
                            <span class="text-xs text-zinc-600">{{ $b->code }}</span>
                        </div>
                        <h2 class="mt-1.5 truncate font-semibold"><a href="{{ route('booking.show', $b) }}" class="hover:underline">{{ $b->space->name }}</a></h2>
                        <p class="text-sm text-zinc-600">{{ $b->starts_at->translatedFormat('D, d M Y') }}@if ($b->start_time), {{ substr($b->start_time, 0, 5) }}@endif &middot; {{ $b->duration }} {{ $b->space->price_unit }}</p>
                        @if ($b->isPayable())
                            <p class="mt-1 text-sm font-medium text-amber-800">Bayar sebelum <span data-countdown="{{ $b->expires_at->toIso8601String() }}" class="tabular-nums">--:--</span></p>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-4 sm:flex-col sm:items-end">
                        <p class="font-semibold">Rp {{ number_format($b->total, 0, ',', '.') }}</p>
                        @if ($b->isPayable())
                            <a href="{{ route('booking.show', $b) }}" class="btn btn-primary btn-sm">Bayar sekarang</a>
                        @elseif ($b->isReviewable())
                            <a href="{{ route('booking.show', $b) }}#ulasan" class="btn btn-outline btn-sm">Tulis ulasan</a>
                        @else
                            <a href="{{ route('booking.show', $b) }}" class="btn btn-outline btn-sm">Detail</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-8">{{ $bookings->links() }}</div>
    @endif
</div>
@endsection
