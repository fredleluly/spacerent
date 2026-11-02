@extends('seller.layout')
@section('title', 'Booking '.$booking->code)
@section('heading', 'Detail Booking')

@section('content')
@php $space = $booking->space; @endphp
<a href="{{ route('seller.bookings.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600"><i data-lucide="arrow-left" class="h-4 w-4"></i>Kembali</a>

@if (session('error'))<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>@endif
<div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <p class="text-xs text-slate-400">Kode Booking</p>
        <p class="text-2xl font-extrabold tracking-widest text-brand-600">{{ $booking->code }}</p>
        <dl class="mt-5 divide-y divide-slate-100 text-sm">
            @foreach ([
                ['Ruangan', $space->name],
                ['Lokasi', $space->location],
                ['Tanggal', $booking->date->translatedFormat('l, d F Y')],
                ['Jam Mulai', $booking->start_time ? substr($booking->start_time, 0, 5) : '-'],
                ['Durasi', $booking->duration.' '.$space->price_unit],
                ['Jumlah Peserta', $booking->guests.' orang'],
                ['Total', 'Rp '.number_format($booking->total, 0, ',', '.')],
                ['Catatan', $booking->notes ?: '-'],
                ['Dibuat', $booking->created_at->format('d M Y H:i')],
            ] as [$k, $v])
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-slate-500">{{ $k }}</dt><dd class="text-right font-semibold">{{ $v }}</dd></div>
            @endforeach
        </dl>
    </section>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="font-bold">Pemesan</h2>
            <p class="mt-3 text-sm font-semibold">{{ $booking->name }}</p>
            @if ($booking->company)<p class="text-sm text-slate-500">{{ $booking->company }}</p>@endif
            <p class="mt-2 text-sm text-slate-500">{{ $booking->email }}</p>
            <p class="text-sm text-slate-500">{{ $booking->phone }}</p>
            <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/\D/', '', $booking->phone)) }}?text={{ rawurlencode("Halo {$booking->name}, terkait booking {$booking->code} di SpaceRent...") }}" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700"><i data-lucide="message-square" class="h-4 w-4"></i>Chat WhatsApp</a>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="font-bold">Transaksi Midtrans</h2>
            <dl class="mt-3 divide-y divide-slate-100 text-sm">
                @foreach ([
                    ['Status Bayar', $paymentStatuses[$booking->payment_status] ?? $booking->payment_status],
                    ['Order ID', $booking->order_id ?: '-'],
                    ['Metode', $booking->payment_type ?: '-'],
                    ['Dibayar', $booking->paid_at ? \Illuminate\Support\Carbon::parse($booking->paid_at)->format('d M Y H:i') : '-'],
                    ['Batas Bayar', $booking->expires_at ? \Illuminate\Support\Carbon::parse($booking->expires_at)->format('d M Y H:i') : '-'],
                    ['Gross Amount', 'Rp '.number_format($booking->total, 0, ',', '.')],
                ] as [$k, $v])
                    <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">{{ $k }}</dt><dd class="break-all text-right font-semibold">{{ $v }}</dd></div>
                @endforeach
            </dl>
            @if ($booking->midtrans_payload)
                @php $payload = is_string($booking->midtrans_payload) ? json_decode($booking->midtrans_payload, true) : $booking->midtrans_payload; @endphp
                <details class="mt-3"><summary class="cursor-pointer text-xs font-bold text-brand-600">Lihat payload</summary>
                    <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-slate-50 p-3 text-[11px]">{{ json_encode($payload ?? $booking->midtrans_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            @endif
        </section>

        @if ($booking->status === 'cancelled' && $booking->payment_status === 'paid')
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-800">
                <p class="font-bold">Refund diproses oleh admin SpaceRent</p>
                <p class="mt-1">Berdasarkan kebijakan ({{ $refundPercent }}%), saran refund: <strong>Rp {{ number_format($refundAmount, 0, ',', '.') }}</strong>.</p>
            </div>
        @endif

        <section class="space-y-2 rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="font-bold">Aksi</h2>
            @if (! in_array($booking->status, ['cancelled', 'completed']))
                <form method="POST" action="{{ route('seller.bookings.cancel', $booking) }}" data-confirm="Batalkan booking {{ $booking->code }}?">@csrf
                    <button class="w-full rounded-lg border border-red-200 py-2.5 text-sm font-bold text-red-600 hover:bg-red-50">Batalkan booking</button>
                    @if ($booking->payment_status === 'paid')<p class="mt-1 text-xs text-amber-700">Sudah lunas: saran refund {{ $refundPercent }}% (Rp {{ number_format($refundAmount, 0, ',', '.') }}). Refund diproses oleh admin SpaceRent.</p>@endif
                </form>
            @endif
            @if ($booking->status === 'confirmed' && $booking->payment_status === 'paid')
                <form method="POST" action="{{ route('seller.bookings.complete', $booking) }}">@csrf
                    <button class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Tandai selesai</button>
                </form>
            @endif
        </section>
    </div>
</div>
@endsection
