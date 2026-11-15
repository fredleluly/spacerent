@extends('layouts.app')

@section('title', 'Booking '.$booking->code.' - SpaceRent')

@section('content')
@php
    $space = $booking->space;
    $isOwner = auth()->id() === $booking->user_id;
    $payable = $isOwner && $booking->isPayable();
    $cancelQuote = $isOwner && $booking->isCancellable() ? \App\Support\RefundPolicy::quote($booking, 'buyer') : null;
    $canReschedule = $isOwner && $booking->status === 'confirmed' && $booking->payment_status === 'paid'
        && $booking->reschedule_count < (int) config('spacerent.max_reschedules', 1)
        && now()->floatDiffInHours($booking->starts_at, false) >= (int) config('spacerent.reschedule_min_hours', 24);
    $snapUrl = config('midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
    $rupiah = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $slotTimes = [];
    if ($space->price_unit === 'jam') {
        for ($t = \Carbon\Carbon::parse($space->open_time); $t->lt(\Carbon\Carbon::parse($space->close_time)); $t->addMinutes(30)) { $slotTimes[] = $t->format('H:i'); }
    }
    $cancelledBy = ['buyer' => 'Anda', 'seller' => 'pemilik ruangan', 'admin' => 'admin SpaceRent', 'system' => 'sistem'][$booking->cancelled_by] ?? null;

    $steps = [
        ['Dipesan', true, $booking->created_at],
        ['Dibayar', $booking->paid_at !== null, $booking->paid_at],
        ['Terkonfirmasi', in_array($booking->status, ['confirmed', 'completed', 'no_show'], true), null],
        ['Selesai', $booking->status === 'completed', null],
    ];
@endphp
<div class="container-page py-8">
    <a href="{{ $isOwner ? route('buyer.bookings.index') : (auth()->user()?->isAdmin() ? route('admin.bookings.show', $booking) : route('seller.bookings.show', $booking)) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-zinc-700 hover:text-zinc-900"><i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali</a>

    @if ($flash = session('payment_flash'))
        <div class="mt-4 rounded-xl border px-4 py-3 text-sm {{ $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : ($flash['type'] === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-sky-200 bg-sky-50 text-sky-900') }}" role="status">{{ $flash['message'] }}</div>
    @endif

    <div class="mt-6 grid gap-8 lg:grid-cols-[1fr_380px] lg:gap-12">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <x-status-badge :booking="$booking" />
                <button type="button" class="inline-flex items-center gap-1.5 rounded-full border border-zinc-300 px-3 py-1 text-xs font-medium text-zinc-700 hover:border-zinc-900" data-copy="{{ $booking->code }}"><i data-lucide="copy" class="h-3 w-3"></i> {{ $booking->code }}</button>
            </div>

            @if ($booking->status === 'awaiting_approval')
                <h1 class="mt-4 text-3xl font-semibold tracking-tight">Menunggu persetujuan pemilik</h1>
                <p class="mt-2 max-w-prose text-zinc-600">Jadwal Anda ditahan sampai {{ $booking->approval_deadline_at?->translatedFormat('d F Y, H:i') }}. Anda baru membayar setelah pemilik menyetujui, dan tidak dikenai biaya jika ditolak.</p>
            @elseif ($payable)
                <h1 class="mt-4 text-3xl font-semibold tracking-tight">Selesaikan pembayaran</h1>
                <p class="mt-2 max-w-prose text-zinc-600">Jadwal ditahan untuk Anda. Sisa waktu <span data-countdown="{{ $booking->expires_at->toIso8601String() }}" class="font-semibold tabular-nums text-amber-800">--:--</span>. Setelah itu booking dibatalkan otomatis dan slot dibuka kembali.</p>
            @elseif ($booking->status === 'pending')
                <h1 class="mt-4 text-3xl font-semibold tracking-tight">Booking kedaluwarsa</h1>
                <p class="mt-2 max-w-prose text-zinc-600">Batas waktu pembayaran sudah lewat. Anda bisa memesan ulang jika jadwal masih tersedia.</p>
            @elseif ($booking->status === 'confirmed')
                <h1 class="mt-4 text-3xl font-semibold tracking-tight">Booking terkonfirmasi</h1>
                <p class="mt-2 max-w-prose text-zinc-600">Sampai jumpa di {{ $space->name }}. Simpan kode {{ $booking->code }} untuk ditunjukkan saat tiba.</p>
                @if ($space->checkin_info)
                    <div class="mt-4 rounded-xl bg-zinc-50 p-4 text-sm"><p class="font-semibold">Info check-in</p><p class="mt-1 whitespace-pre-line text-zinc-700">{{ $space->checkin_info }}</p></div>
                @endif
            @elseif ($booking->status === 'completed')
                <h1 class="mt-4 text-3xl font-semibold tracking-tight">Sewa selesai</h1>
                <p class="mt-2 max-w-prose text-zinc-600">Terima kasih sudah memakai {{ $space->name }}.@if ($booking->isReviewable()) Bagikan pengalaman Anda di bawah.@endif</p>
            @elseif ($booking->status === 'cancelled')
                <h1 class="mt-4 text-3xl font-semibold tracking-tight">Booking dibatalkan</h1>
                <p class="mt-2 max-w-prose text-zinc-600">Dibatalkan oleh {{ $cancelledBy ?? 'sistem' }}@if ($booking->cancel_reason): {{ $booking->cancel_reason }}@endif.</p>
                @if ($booking->refund_status === 'pending')
                    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">Refund {{ $rupiah($booking->refund_amount) }} sedang diproses ke metode pembayaran Anda.</div>
                @elseif ($booking->refund_status === 'refunded')
                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">Refund {{ $rupiah($booking->refund_amount) }} sudah dikirim.</div>
                @elseif ($booking->paid_at)
                    <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700">Tidak ada pengembalian dana sesuai kebijakan pembatalan.</div>
                @endif
            @else
                <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $booking->status_label }}</h1>
            @endif

            @if ($booking->status !== 'cancelled')
                <ol class="mt-8 grid grid-cols-4 gap-2" aria-label="Progres booking">
                    @foreach ($steps as [$label, $done, $at])
                        <li>
                            <div class="h-1.5 rounded-full {{ $done ? 'bg-zinc-900' : 'bg-zinc-200' }}"></div>
                            <p class="mt-2 text-xs font-medium {{ $done ? 'text-zinc-900' : 'text-zinc-500' }}">{{ $label }}</p>
                            @if ($at)<p class="text-xs text-zinc-600">{{ $at->translatedFormat('d M, H:i') }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($payable)
                <div class="mt-8">
                    <button type="button" id="pay-btn" class="btn btn-primary btn-lg w-full sm:w-auto"><i data-lucide="credit-card" class="h-4 w-4"></i> Bayar {{ $rupiah($booking->total) }}</button>
                    <p id="pay-error" class="error hidden" role="alert"></p>
                </div>
            @endif

            {{-- Ulasan --}}
            <section id="ulasan" class="scroll-mt-24 mt-12 border-t border-zinc-200 pt-8">
                @if ($booking->review)
                    @php $rv = $booking->review; @endphp
                    <h2 class="text-xl font-semibold">Ulasan Anda</h2>
                    <div class="mt-4 rounded-2xl border border-zinc-200 p-5">
                        <div class="flex items-center justify-between gap-3"><x-stars :value="$rv->rating" /><span class="text-xs text-zinc-600">{{ $rv->created_at->translatedFormat('d F Y') }}@if ($rv->is_anonymous), tampil anonim @endif</span></div>
                        @if ($rv->comment)<p class="mt-3 whitespace-pre-line text-sm leading-relaxed">{{ $rv->comment }}</p>@endif
                        @if ($rv->photos->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">@foreach ($rv->photos as $p)<img src="{{ $p->url }}" alt="Foto ulasan" class="h-20 w-20 rounded-xl object-cover">@endforeach</div>
                        @endif
                        @if ($rv->reply)<div class="mt-4 rounded-xl bg-zinc-50 p-4 text-sm"><p class="font-semibold">Balasan pemilik</p><p class="mt-1 whitespace-pre-line text-zinc-700">{{ $rv->reply }}</p></div>@endif
                        @if ($isOwner && $rv->isEditable())
                            <details class="mt-4">
                                <summary class="cursor-pointer text-sm font-semibold underline underline-offset-2">Ubah ulasan</summary>
                                <form method="POST" action="{{ route('reviews.update', $rv) }}" class="mt-4 space-y-4" data-once>
                                    @csrf @method('PUT')
                                    <div class="star-input flex gap-1" data-stars data-name="rating" data-value="{{ $rv->rating }}"></div>
                                    <textarea name="comment" rows="3" maxlength="1000" class="input">{{ $rv->comment }}</textarea>
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_anonymous" value="1" class="h-4 w-4 accent-brand-600" @checked($rv->is_anonymous)> Tampilkan sebagai anonim</label>
                                    <button class="btn btn-dark btn-sm">Simpan perubahan</button>
                                </form>
                            </details>
                        @endif
                    </div>
                @elseif ($isOwner && $booking->isReviewable())
                    <h2 class="text-xl font-semibold">Bagaimana pengalaman Anda?</h2>
                    <p class="mt-1 text-sm text-zinc-600">Ulasan Anda membantu penyewa lain. Anda boleh menambahkan foto dan memilih tampil anonim.</p>
                    <form method="POST" action="{{ route('reviews.store', $booking) }}" enctype="multipart/form-data" class="mt-6 space-y-6" data-once>
                        @csrf
                        <div>
                            <p class="label">Rating keseluruhan</p>
                            <div class="star-input flex gap-1" data-stars data-name="rating" data-value="{{ old('rating') }}"></div>
                            @error('rating')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <details class="rounded-xl border border-zinc-200">
                            <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold [&::-webkit-details-marker]:hidden">Nilai per aspek (opsional)</summary>
                            <div class="grid gap-4 border-t border-zinc-200 p-4 sm:grid-cols-2">
                                @foreach (\App\Models\Review::SUB_RATINGS as $key => $label)
                                    <div><p class="mb-1 text-sm text-zinc-700">{{ $label }}</p><div class="star-input flex gap-0.5" data-stars data-name="{{ $key }}" data-value="{{ old($key) }}"></div></div>
                                @endforeach
                            </div>
                        </details>
                        <div>
                            <label for="comment" class="label">Ceritakan pengalaman Anda</label>
                            <textarea id="comment" name="comment" rows="4" maxlength="1000" class="input" placeholder="Kebersihan, fasilitas, kemudahan akses, keramahan pemilik...">{{ old('comment') }}</textarea>
                            @error('comment')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="photos" class="label">Foto (opsional, maks. 4 foto, 3 MB per foto)</label>
                            <input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-preview="#photo-preview" class="block w-full text-sm text-zinc-700 file:mr-4 file:rounded-xl file:border-0 file:bg-zinc-900 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white hover:file:bg-zinc-800">
                            <div id="photo-preview" class="mt-3 flex flex-wrap gap-2"></div>
                            @error('photos')<p class="error">{{ $message }}</p>@enderror
                            @error('photos.*')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-start gap-2.5 text-sm text-zinc-700"><input type="checkbox" name="is_anonymous" value="1" class="mt-0.5 h-4 w-4 rounded accent-brand-600" @checked(old('is_anonymous'))> <span>Tampilkan sebagai anonim. Nama Anda disembunyikan dari publik, tetapi tetap tersimpan untuk keperluan moderasi.</span></label>
                        <button type="submit" class="btn btn-primary btn-lg" data-loading="Mengirim...">Kirim ulasan</button>
                    </form>
                @elseif ($isOwner && $booking->status === 'completed')
                    <p class="text-sm text-zinc-600">Masa menulis ulasan untuk booking ini sudah berakhir.</p>
                @endif
            </section>
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <div class="card overflow-hidden shadow-soft">
                <a href="{{ route('spaces.show', $space) }}" class="block aspect-[16/9] bg-zinc-100"><img src="{{ $space->image_url }}" alt="" class="h-full w-full object-cover"></a>
                <div class="space-y-4 p-5">
                    <div>
                        <h2 class="font-semibold">{{ $space->name }}</h2>
                        <p class="text-sm text-zinc-600">{{ $space->location }}</p>
                    </div>
                    <dl class="divide-y divide-zinc-100 text-sm">
                        <div class="flex justify-between gap-4 py-2.5"><dt class="text-zinc-600">Tanggal</dt><dd class="text-right font-medium">{{ $booking->starts_at->translatedFormat('l, d F Y') }}</dd></div>
                        <div class="flex justify-between gap-4 py-2.5"><dt class="text-zinc-600">Waktu</dt><dd class="text-right font-medium">@if ($space->price_unit === 'jam'){{ $booking->starts_at->format('H:i') }} - {{ $booking->end_at->format('H:i') }}@else{{ $booking->duration }} {{ $space->price_unit }}, sampai {{ $booking->end_at->translatedFormat('d M Y') }}@endif</dd></div>
                        <div class="flex justify-between gap-4 py-2.5"><dt class="text-zinc-600">Peserta</dt><dd class="font-medium">{{ $booking->guests }} orang</dd></div>
                        <div class="flex justify-between gap-4 py-2.5"><dt class="text-zinc-600">Pemesan</dt><dd class="text-right font-medium">{{ $booking->name }}</dd></div>
                    </dl>

                    <div class="space-y-1.5 border-t border-zinc-200 pt-4 text-sm">
                        @if ($booking->subtotal > 0)
                            <div class="flex justify-between"><span class="text-zinc-600">Sewa ({{ $booking->duration }} {{ $space->price_unit }})</span><span>{{ $rupiah($booking->subtotal) }}</span></div>
                            @if ($booking->cleaning_fee > 0)<div class="flex justify-between"><span class="text-zinc-600">Biaya kebersihan</span><span>{{ $rupiah($booking->cleaning_fee) }}</span></div>@endif
                            @if ($booking->service_fee > 0)<div class="flex justify-between"><span class="text-zinc-600">Biaya layanan</span><span>{{ $rupiah($booking->service_fee) }}</span></div>@endif
                        @endif
                        <div class="flex justify-between pt-2 text-base font-semibold"><span>Total</span><span>{{ $rupiah($booking->total) }}</span></div>
                        @if ($booking->paid_at)<p class="pt-1 text-xs text-zinc-600">Dibayar {{ $booking->paid_at->translatedFormat('d M Y, H:i') }}@if ($booking->payment_type) lewat {{ str_replace('_', ' ', $booking->payment_type) }}@endif</p>@endif
                    </div>

                    <div class="space-y-2 border-t border-zinc-200 pt-4">
                        @if ($payable)
                            <button type="button" class="btn btn-primary w-full" onclick="document.getElementById('pay-btn').click()">Bayar sekarang</button>
                        @endif
                        @if ($isOwner)
                            <form method="POST" action="{{ route('chat.start.booking', $booking) }}">@csrf
                                <button class="btn btn-outline w-full"><i data-lucide="message-circle" class="h-4 w-4"></i> Chat pemilik</button>
                            </form>
                        @endif
                        @if ($canReschedule)
                            <button type="button" class="btn btn-outline w-full" data-dialog-open="#reschedule-dialog"><i data-lucide="calendar-days" class="h-4 w-4"></i> Ubah jadwal</button>
                        @endif
                        @if ($isOwner && $cancelQuote !== null)
                            <button type="button" class="btn btn-danger w-full" data-dialog-open="#cancel-dialog">Batalkan booking</button>
                        @endif
                        <a href="https://wa.me/{{ config('spacerent.whatsapp') }}?text={{ rawurlencode('Halo SpaceRent, saya butuh bantuan untuk booking '.$booking->code) }}" target="_blank" rel="noopener" class="block pt-1 text-center text-xs font-medium text-zinc-600 underline underline-offset-2">Butuh bantuan? Hubungi kami</a>
                    </div>
                </div>
            </div>
            <p class="mt-4 text-xs leading-relaxed text-zinc-600">Kebijakan pembatalan {{ \App\Support\RefundPolicy::label($booking->cancellation_policy) }}: {{ \App\Support\RefundPolicy::summary($booking->cancellation_policy) }}</p>
        </aside>
    </div>
</div>

@if ($cancelQuote !== null)
<dialog id="cancel-dialog" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl p-0 shadow-lift">
    <form method="POST" action="{{ route('booking.cancel', $booking) }}" class="p-6" data-once>
        @csrf
        <h2 class="text-lg font-semibold">Batalkan booking ini?</h2>
        @if ($booking->payment_status === 'paid')
            <div class="mt-4 rounded-xl bg-zinc-50 p-4 text-sm">
                <p>Sesuai kebijakan {{ \App\Support\RefundPolicy::label($booking->cancellation_policy) }}, Anda mendapat refund <strong>{{ $cancelQuote['percent'] }}%</strong>.</p>
                <p class="mt-1 text-lg font-semibold">{{ $rupiah($cancelQuote['amount']) }}</p>
                @if ($cancelQuote['percent'] === 0)<p class="mt-1 text-rose-700">Anda tidak akan menerima pengembalian dana.</p>@endif
            </div>
        @else
            <p class="mt-3 text-sm text-zinc-600">Anda belum membayar, jadi tidak ada biaya. Jadwal akan dilepas.</p>
        @endif
        <label for="reason" class="label mt-4">Alasan (opsional)</label>
        <input id="reason" name="reason" maxlength="200" class="input">
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="btn btn-ghost" data-dialog-close>Tidak jadi</button>
            <button type="submit" class="btn btn-danger" data-loading="Memproses...">Ya, batalkan</button>
        </div>
    </form>
</dialog>
@endif

@if ($canReschedule)
<dialog id="reschedule-dialog" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl p-0 shadow-lift">
    <form method="POST" action="{{ route('booking.reschedule', $booking) }}" class="p-6" data-once>
        @csrf
        <h2 class="text-lg font-semibold">Ubah jadwal</h2>
        <p class="mt-1 text-sm text-zinc-600">Durasi dan harga tetap. Jadwal hanya bisa diubah {{ config('spacerent.max_reschedules', 1) }} kali, minimal {{ config('spacerent.reschedule_min_hours', 24) }} jam sebelum mulai.</p>
        <div class="mt-4">
            <label for="rs-date" class="label">Tanggal baru</label>
            <input id="rs-date" type="date" name="date" min="{{ today()->toDateString() }}" required class="input">
        </div>
        @if ($slotTimes)
            <div class="mt-4">
                <label for="rs-time" class="label">Jam mulai</label>
                <select id="rs-time" name="start_time" required class="input">
                    @foreach ($slotTimes as $t)<option value="{{ $t }}" @selected($t === $booking->starts_at->format('H:i'))>{{ $t }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
            <button type="submit" class="btn btn-dark" data-loading="Memeriksa...">Ubah jadwal</button>
        </div>
    </form>
</dialog>
@endif
@endsection

@if ($payable)
@push('scripts')
<script src="{{ $snapUrl }}" data-client-key="{{ config('midtrans.client_key') }}"></script>
<script>
(function () {
    const finishUrl = @json(route('booking.finish', $booking));
    const payUrl = @json(route('booking.pay', $booking));
    const btn = document.getElementById('pay-btn');
    const err = document.getElementById('pay-error');
    const showError = (m) => { err.textContent = m; err.classList.remove('hidden'); };
    btn.addEventListener('click', async () => {
        err.classList.add('hidden');
        btn.disabled = true;
        try {
            const res = await fetch(payUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' } });
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
