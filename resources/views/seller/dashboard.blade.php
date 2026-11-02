@extends('seller.layout')
@section('title', 'Dashboard')
@section('heading', 'Dashboard Penjual')

@section('content')
@if (session('error'))<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>@endif
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ([
        ['Jumlah Ruangan', $stats['spaces'], 'building-2', 'text-brand-600 bg-brand-50'],
        ['Total Pendapatan (Lunas)', 'Rp '.number_format($stats['revenue_paid'], 0, ',', '.'), 'banknote', 'text-emerald-600 bg-emerald-50'],
        ['Pendapatan Bulan Ini', 'Rp '.number_format($stats['revenue_month'], 0, ',', '.'), 'trending-up', 'text-brand-600 bg-brand-50'],
        ['Menunggu Pembayaran', $stats['awaiting_payment'], 'hourglass', 'text-amber-600 bg-amber-50'],
        ['Booking Mendatang', $stats['upcoming'], 'calendar-days', 'text-sky-600 bg-sky-50'],
    ] as [$label, $value, $icon, $color])
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl {{ $color }}"><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
            <div class="min-w-0"><p class="text-xs text-slate-500">{{ $label }}</p><p class="truncate text-xl font-extrabold">{{ $value }}</p></div>
        </div>
    @endforeach
</div>

<section class="mt-6 rounded-xl border border-slate-200 bg-white">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <h2 class="font-bold">Booking Terbaru</h2>
        <a href="{{ route('seller.bookings.index') }}" class="text-xs font-bold text-brand-600">Lihat semua</a>
    </div>
    @include('seller.partials.booking-table', ['bookings' => $latestBookings])
</section>
@endsection
