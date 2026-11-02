@extends('layouts.app')

@section('title', 'Booking '.$booking->code.' - SpaceRent')

@section('content')
@php
    $space = $booking->space;
    $msg = "Halo SpaceRent, saya ingin konfirmasi booking {$booking->code}:\n"
        ."Ruangan: {$space->name}\n"
        .'Tanggal: '.$booking->date->translatedFormat('d F Y').($booking->start_time ? ' '.substr($booking->start_time, 0, 5) : '')."\n"
        ."Durasi: {$booking->duration} {$space->price_unit}\n"
        .'Total: Rp '.number_format($booking->total, 0, ',', '.')."\n"
        ."Atas nama: {$booking->name}";
    $payState = $booking->payment_status ?? 'unpaid';
    $expiresAt = $booking->expires_at ? \Illuminate\Support\Carbon::parse($booking->expires_at) : null;
    if ($payState === 'unpaid' && $expiresAt && $expiresAt->isPast()) { $payState = 'expired'; }
    if ($payState === 'unpaid' && $booking->status === 'cancelled') { $payState = 'failed'; }
    $isPaid = $payState === 'paid';
    $isPayable = $payState === 'unpaid';
    $badge = [
        'unpaid' => ['Menunggu Pembayaran', 'bg-amber-100 text-amber-700'],
        'paid' => ['Lunas', 'bg-emerald-100 text-emerald-700'],
        'expired' => ['Kedaluwarsa', 'bg-slate-200 text-slate-600'],
        'failed' => ['Gagal', 'bg-rose-100 text-rose-700'],
        'refunded' => ['Dikembalikan', 'bg-sky-100 text-sky-700'],
    ][$payState] ?? ['Menunggu Pembayaran', 'bg-amber-100 text-amber-700'];
    $snapUrl = config('midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
    $waLink = 'https://wa.me/'.config('spacerent.whatsapp').'?text='.rawurlencode($msg);
@endphp
<div class="bg-slate-50 py-14">
    <div class="mx-auto max-w-2xl px-4">
        @if (auth()->user()?->isBuyer())
            <a href="{{ route('buyer.bookings.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-brand-600"><i data-lucide="arrow-left" class="h-4 w-4"></i> Pesanan Saya</a>
        @endif
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-lg">
            <div class="text-center">
                @if ($isPaid)
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><i data-lucide="check" class="h-7 w-7"></i></span>
                    <h1 class="mt-4 text-2xl font-extrabold">Pembayaran Berhasil</h1>
                    <p class="mt-1 text-sm text-slate-500">Booking Anda sudah dikonfirmasi. Simpan kode booking berikut.</p>
                @elseif ($isPayable)
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600"><i data-lucide="clock" class="h-7 w-7"></i></span>
                    <h1 class="mt-4 text-2xl font-extrabold">Selesaikan Pembayaran</h1>
                    <p class="mt-1 text-sm text-slate-500">Slot ditahan sampai batas waktu bayar. Setelah itu booking otomatis dibatalkan.</p>
                @else
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-rose-600"><i data-lucide="x" class="h-7 w-7"></i></span>
                    <h1 class="mt-4 text-2xl font-extrabold">{{ $payState === 'expired' ? 'Booking Kedaluwarsa' : 'Pembayaran Gagal' }}</h1>
                    <p class="mt-1 text-sm text-slate-500">Booking ini tidak dapat dibayar lagi. Silakan buat booking baru.</p>
                @endif
                <p class="mt-5 inline-block rounded-xl bg-brand-50 px-6 py-3 text-2xl font-extrabold tracking-widest text-brand-600">{{ $booking->code }}</p>
            </div>

            @if ($flash = session('payment_flash'))
                <div class="mt-6 rounded-lg border px-4 py-3 text-sm {{ $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($flash['type'] === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-sky-200 bg-sky-50 text-sky-700') }}">{{ $flash['message'] }}</div>
            @endif

            <dl class="mt-8 divide-y divide-slate-100 text-sm">
                @foreach ([
                    ['Ruangan', $space->name],
                    ['Lokasi', $space->location],
                    ['Tanggal', $booking->date->translatedFormat('l, d F Y')],
                    ['Jam Mulai', $booking->start_time ? substr($booking->start_time, 0, 5).' WIB' : '-'],
                    ['Durasi', $booking->duration.' '.$space->price_unit],
                    ['Jumlah Peserta', $booking->guests.' orang'],
                    ['Nama', $booking->name],
                ] as [$k, $v])
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">{{ $k }}</dt><dd class="text-right font-semibold">{{ $v }}</dd></div>
                @endforeach
                <div class="flex items-center justify-between gap-4 py-3"><dt class="text-slate-500">Status Pembayaran</dt><dd><span class="rounded-full px-3 py-1 text-xs font-bold {{ $badge[1] }}">{{ $badge[0] }}</span></dd></div>
                @if ($isPayable && $expiresAt)
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Sisa Waktu Bayar</dt><dd class="font-mono font-bold text-amber-600" id="countdown" data-expires="{{ $expiresAt->toIso8601String() }}">--:--</dd></div>
                @endif
                <div class="flex justify-between gap-4 py-3"><dt class="font-bold">Total</dt><dd class="text-lg font-extrabold text-brand-600">Rp {{ number_format($booking->total, 0, ',', '.') }}</dd></div>
            </dl>

            <div class="mt-6 flex flex-col gap-3">
                @if ($isPayable)
                    <button type="button" id="pay-btn" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-3 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-60">
                        <i data-lucide="credit-card" class="h-4 w-4"></i> <span>Bayar Sekarang</span>
                    </button>
                    <p id="pay-error" class="hidden text-center text-sm text-rose-600"></p>
                @endif
                <div class="flex flex-col gap-3 sm:flex-row">
                    @if (auth()->user()?->isBuyer() && $booking->user_id === auth()->id())
                    <form method="POST" action="{{ route('chat.start.booking', $booking) }}" class="flex flex-1">
                        @csrf
                        <button class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-brand-600 px-4 py-3 text-sm font-bold text-brand-600 hover:bg-brand-50"><i data-lucide="message-circle" class="h-4 w-4"></i> Chat Pemilik</button>
                    </form>
                    @endif
                    <a href="{{ $waLink }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-emerald-200 px-4 py-3 text-sm font-bold text-emerald-700 hover:bg-emerald-50">
                        <i data-lucide="message-square" class="h-4 w-4"></i> Butuh bantuan? WhatsApp
                    </a>
                    <a href="{{ route('home') }}" class="inline-flex flex-1 items-center justify-center rounded-lg border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">Kembali ke Beranda</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@if ($isPayable)
@push('scripts')
<script src="{{ $snapUrl }}" data-client-key="{{ config('midtrans.client_key') }}"></script>
<script>
(function () {
    const finishUrl = @json(route('booking.finish', $booking));
    const payUrl = @json(route('booking.pay', $booking));
    const btn = document.getElementById('pay-btn');
    const err = document.getElementById('pay-error');
    const cd = document.getElementById('countdown');

    if (cd) {
        const end = new Date(cd.dataset.expires).getTime();
        const tick = () => {
            const left = Math.floor((end - Date.now()) / 1000);
            if (left <= 0) { cd.textContent = 'Habis'; window.location.reload(); return; }
            cd.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
        };
        tick(); setInterval(tick, 1000);
    }

    const showError = (m) => { err.textContent = m; err.classList.remove('hidden'); };
    btn.addEventListener('click', async () => {
        err.classList.add('hidden');
        btn.disabled = true;
        try {
            const res = await fetch(payUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token()), 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) { showError(data.message || 'Gagal memulai pembayaran.'); btn.disabled = false; return; }
            window.snap.pay(data.snap_token, {
                onSuccess: () => { window.location.href = finishUrl; },
                onPending: () => { window.location.href = finishUrl; },
                onError: () => { showError('Pembayaran gagal. Silakan coba lagi.'); btn.disabled = false; },
                onClose: () => { btn.disabled = false; },
            });
        } catch (e) {
            showError('Terjadi kesalahan jaringan. Coba lagi.'); btn.disabled = false;
        }
    });
})();
</script>
@endpush
@endif
