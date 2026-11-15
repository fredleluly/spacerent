@php
    $isAdmin = $panel === 'admin';
    $rupiah = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $space = $booking->space;
    $canApprove = ! $isAdmin && $booking->isAwaitingApproval();
    $canCancel = $booking->isActive() && ($isAdmin || ! $booking->hasStarted());
    $canComplete = $booking->status === 'confirmed' && $booking->payment_status === 'paid' && $booking->hasStarted();
    $by = ['buyer' => 'pembeli', 'seller' => 'penjual', 'admin' => 'admin', 'system' => 'sistem'][$booking->cancelled_by] ?? null;
@endphp
<a href="{{ route($panel.'.bookings.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-700 hover:text-zinc-900"><i data-lucide="arrow-left" class="h-4 w-4"></i> Semua booking</a>

<div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
    <div class="space-y-6">
        <section class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold">{{ $booking->code }}</h2>
                    <x-status-badge :booking="$booking" />
                    @if ($booking->refund_status === 'pending')<span class="badge badge-info">Menunggu refund</span>@endif
                </div>
                <a href="{{ route('booking.show', $booking) }}" class="text-sm font-medium text-brand-700 underline underline-offset-2">Lihat sebagai pembeli</a>
            </div>
            @if ($booking->status === 'awaiting_approval')
                <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Menunggu keputusan sebelum {{ $booking->approval_deadline_at?->translatedFormat('d F Y, H:i') }}. Jika terlewat, permintaan dibatalkan otomatis.</p>
            @endif
            @if ($booking->status === 'cancelled')
                <p class="mt-3 text-sm text-zinc-700">Dibatalkan oleh {{ $by ?? 'sistem' }} pada {{ $booking->cancelled_at?->translatedFormat('d F Y, H:i') }}@if ($booking->cancel_reason). Alasan: {{ $booking->cancel_reason }}@endif.</p>
            @endif

            <dl class="mt-5 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
                <div><dt class="text-zinc-600">Ruangan</dt><dd class="font-medium">{{ $space->name }}</dd></div>
                <div><dt class="text-zinc-600">Jadwal</dt><dd class="font-medium">{{ $booking->starts_at->translatedFormat('D, d M Y') }}@if ($booking->start_time), {{ substr($booking->start_time, 0, 5) }} - {{ $booking->end_at->format('H:i') }}@else sampai {{ $booking->end_at->translatedFormat('d M Y') }}@endif</dd></div>
                <div><dt class="text-zinc-600">Durasi</dt><dd class="font-medium">{{ $booking->duration }} {{ $space->price_unit }}</dd></div>
                <div><dt class="text-zinc-600">Peserta</dt><dd class="font-medium">{{ $booking->guests }} orang</dd></div>
                <div><dt class="text-zinc-600">Pemesan</dt><dd class="font-medium">{{ $booking->name }}@if ($booking->company), {{ $booking->company }}@endif</dd></div>
                <div><dt class="text-zinc-600">Kontak</dt><dd class="font-medium">{{ $booking->email }}<br>{{ $booking->phone }}</dd></div>
                @if ($booking->notes)<div class="sm:col-span-2"><dt class="text-zinc-600">Catatan</dt><dd class="whitespace-pre-line font-medium">{{ $booking->notes }}</dd></div>@endif
            </dl>
        </section>

        @if ($booking->review)
            <section class="card p-6">
                <h2 class="font-semibold">Ulasan pembeli</h2>
                <div class="mt-3 flex items-center gap-2"><x-stars :value="$booking->review->rating" /> <span class="text-xs text-zinc-600">{{ $booking->review->created_at->translatedFormat('d M Y') }}@if ($booking->review->is_anonymous), anonim @endif</span></div>
                @if ($booking->review->comment)<p class="mt-2 whitespace-pre-line text-sm">{{ $booking->review->comment }}</p>@endif
                @if ($booking->review->reply)<p class="mt-3 rounded-xl bg-zinc-50 p-3 text-sm"><span class="font-semibold">Balasan:</span> {{ $booking->review->reply }}</p>@endif
            </section>
        @endif
    </div>

    <div class="space-y-6">
        <section class="card p-6">
            <h2 class="font-semibold">Pembayaran</h2>
            <dl class="mt-4 space-y-2 text-sm">
                @if ($booking->subtotal > 0)
                    <div class="flex justify-between"><dt class="text-zinc-600">Sewa</dt><dd>{{ $rupiah($booking->subtotal) }}</dd></div>
                    @if ($booking->cleaning_fee)<div class="flex justify-between"><dt class="text-zinc-600">Biaya kebersihan</dt><dd>{{ $rupiah($booking->cleaning_fee) }}</dd></div>@endif
                    @if ($booking->service_fee)<div class="flex justify-between"><dt class="text-zinc-600">Biaya layanan</dt><dd>{{ $rupiah($booking->service_fee) }}</dd></div>@endif
                @endif
                <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold"><dt>Total</dt><dd>{{ $rupiah($booking->total) }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-600">Status uang</dt><dd class="font-medium">{{ $booking->payment_label }}</dd></div>
                @if ($booking->payment_type)<div class="flex justify-between"><dt class="text-zinc-600">Metode</dt><dd>{{ str_replace('_', ' ', $booking->payment_type) }}</dd></div>@endif
                @if ($booking->paid_at)<div class="flex justify-between"><dt class="text-zinc-600">Dibayar</dt><dd>{{ $booking->paid_at->translatedFormat('d M Y, H:i') }}</dd></div>@endif
                @if ($isAdmin && $booking->order_id)<div class="flex justify-between"><dt class="text-zinc-600">Order ID</dt><dd class="break-all text-xs">{{ $booking->order_id }}</dd></div>@endif
                <div class="flex justify-between"><dt class="text-zinc-600">Kebijakan batal</dt><dd>{{ \App\Support\RefundPolicy::label($booking->cancellation_policy) }}</dd></div>
            </dl>
            @if ($booking->refund_status !== 'none')
                <div class="mt-4 rounded-xl bg-sky-50 p-3 text-sm text-sky-900">Refund {{ $rupiah($booking->refund_amount) }}: {{ $booking->refund_status === 'pending' ? 'menunggu diproses' : 'sudah dikirim' }}.</div>
            @endif
        </section>

        <section class="card space-y-3 p-6">
            <h2 class="font-semibold">Tindakan</h2>

            @if ($canApprove)
                <form method="POST" action="{{ route('seller.bookings.approve', $booking) }}" data-once>@csrf
                    <button class="btn btn-primary w-full" data-loading="Memproses...">Setujui permintaan</button>
                </form>
                <form method="POST" action="{{ route('seller.bookings.reject', $booking) }}" data-once class="space-y-2">@csrf
                    <input name="reason" maxlength="200" placeholder="Alasan penolakan (opsional)" class="input">
                    <button class="btn btn-danger w-full">Tolak permintaan</button>
                </form>
            @endif

            @if ($canComplete)
                <form method="POST" action="{{ route($panel.'.bookings.complete', $booking) }}" data-once>@csrf
                    <button class="btn btn-dark w-full">Tandai selesai</button>
                </form>
                <form method="POST" action="{{ route($panel.'.bookings.no-show', $booking) }}" data-confirm="Tandai penyewa tidak hadir? Tidak ada refund untuk status ini.">@csrf
                    <button class="btn btn-outline w-full">Tandai tidak hadir</button>
                </form>
            @endif

            @if ($canCancel)
                <form method="POST" action="{{ route($panel.'.bookings.cancel', $booking) }}" data-once data-confirm="Batalkan booking ini?{{ $booking->payment_status === 'paid' ? ' Pembeli akan menerima refund penuh.' : '' }}" class="space-y-2">@csrf
                    <input name="reason" maxlength="200" placeholder="Alasan pembatalan" class="input">
                    <button class="btn btn-danger w-full">Batalkan booking</button>
                    @if ($booking->payment_status === 'paid')<p class="help">Pembatalan oleh {{ $isAdmin ? 'admin' : 'penjual' }} selalu mengembalikan dana penuh ke pembeli.</p>@endif
                </form>
            @endif

            @if ($isAdmin && $booking->refund_status === 'pending')
                <form method="POST" action="{{ route('admin.bookings.refund', $booking) }}" data-confirm="Kirim refund {{ $rupiah($booking->refund_amount) }} lewat Midtrans?">@csrf
                    <input type="hidden" name="via" value="gateway">
                    <button class="btn btn-primary w-full">Refund lewat Midtrans</button>
                </form>
                <form method="POST" action="{{ route('admin.bookings.refund', $booking) }}" data-confirm="Tandai refund sudah dikirim manual?">@csrf
                    <button class="btn btn-outline w-full">Tandai sudah direfund manual</button>
                </form>
            @endif

            @if ($isAdmin && ! $booking->paid_at && ! $booking->isActive())
                <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" data-confirm="Arsipkan booking ini?">@csrf @method('DELETE')
                    <button class="btn btn-ghost w-full text-rose-700">Arsipkan booking</button>
                </form>
            @endif

            @if (! $canApprove && ! $canComplete && ! $canCancel && ! ($isAdmin && $booking->refund_status === 'pending'))
                <p class="text-sm text-zinc-600">Tidak ada tindakan yang tersedia untuk status ini.</p>
            @endif
        </section>
    </div>
</div>
