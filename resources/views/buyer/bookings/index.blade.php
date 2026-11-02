@extends('layouts.app')

@section('title', 'Pesanan Saya - SpaceRent')

@section('content')
@include('buyer._nav')
@php
    $tabs = ['all' => 'Semua', 'unpaid' => 'Menunggu Bayar', 'confirmed' => 'Terkonfirmasi', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
    $statusBadge = [
        'pending' => ['Menunggu', 'bg-amber-100 text-amber-700'],
        'confirmed' => ['Terkonfirmasi', 'bg-emerald-100 text-emerald-700'],
        'completed' => ['Selesai', 'bg-sky-100 text-sky-700'],
        'cancelled' => ['Dibatalkan', 'bg-slate-200 text-slate-600'],
    ];
    $payBadge = [
        'unpaid' => ['Belum Dibayar', 'bg-amber-100 text-amber-700'],
        'paid' => ['Lunas', 'bg-emerald-100 text-emerald-700'],
        'expired' => ['Kedaluwarsa', 'bg-slate-200 text-slate-600'],
        'failed' => ['Gagal', 'bg-rose-100 text-rose-700'],
        'refunded' => ['Dikembalikan', 'bg-sky-100 text-sky-700'],
    ];
@endphp
<div class="bg-slate-50 py-10">
    <div class="mx-auto max-w-4xl px-4">
        <h1 class="text-2xl font-extrabold">Pesanan Saya</h1>

        <div class="mt-5 flex flex-wrap gap-2">
            @foreach ($tabs as $key => $name)
                <a href="{{ route('buyer.bookings.index', $key === 'all' ? [] : ['status' => $key]) }}" class="rounded-full border px-4 py-1.5 text-xs font-bold {{ $tab === $key ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-brand-500' }}">{{ $name }}</a>
            @endforeach
        </div>

        <div class="mt-6 space-y-4">
            @forelse ($bookings as $b)
                @php
                    $s = $b->space;
                    $pay = $b->payment_status ?? 'unpaid';
                    if ($pay === 'unpaid' && $b->expires_at && $b->expires_at->isPast()) { $pay = 'expired'; }
                    $st = $b->status === 'cancelled' && $pay === 'unpaid' ? 'failed' : $pay;
                    $sb = $statusBadge[$b->status] ?? [ucfirst($b->status), 'bg-slate-100 text-slate-600'];
                    $pb = $payBadge[$pay] ?? ['-', 'bg-slate-100 text-slate-600'];
                @endphp
                <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row">
                    @if ($s)<img src="{{ $s->image_url }}" alt="{{ $s->name }}" class="h-32 w-full rounded-xl object-cover sm:h-28 sm:w-40">@endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-extrabold">{{ $s->name ?? 'Ruangan dihapus' }}</p>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $sb[1] }}">{{ $sb[0] }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $pb[1] }}">{{ $pb[0] }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">Kode: <span class="font-bold text-slate-600">{{ $b->code }}</span></p>
                        <p class="mt-1 text-sm text-slate-600">{{ $b->date->translatedFormat('d F Y') }}@if ($b->start_time), {{ substr($b->start_time, 0, 5) }} WIB @endif &middot; {{ $b->duration }} {{ $s->price_unit ?? '' }}</p>
                        <p class="mt-1 text-lg font-extrabold text-brand-600">Rp {{ number_format($b->total, 0, ',', '.') }}</p>
                        @if ($b->isPayable())
                            <p class="mt-1 text-xs font-semibold text-amber-600">Bayar sebelum {{ $b->expires_at->translatedFormat('d M Y H:i') }} ({{ $b->expires_at->diffForHumans() }})</p>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($b->isPayable())
                                <a href="{{ route('booking.show', $b) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-bold text-white hover:bg-brand-700">Bayar Sekarang</a>
                            @endif
                            <a href="{{ route('booking.show', $b) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 hover:border-brand-500">Detail</a>
                            @if ($s)
                                <a href="{{ route('booking.show', $b) }}#chat" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 hover:border-brand-500">Chat Penjual</a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                    <p class="font-bold">Belum ada pesanan</p>
                    <a href="{{ route('home') }}#ruangan" class="mt-3 inline-block rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-bold text-white">Cari Ruangan</a>
                </div>
            @endforelse
        </div>

        <div class="mt-6">{{ $bookings->links() }}</div>
    </div>
</div>
@endsection
