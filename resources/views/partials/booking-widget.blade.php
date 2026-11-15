@php
    $user = auth()->user();
    $canBook = $user && $user->isBuyer();
    $cfg = [
        'url' => route('spaces.availability', $space),
        'unit' => $space->price_unit,
        'price' => (int) $space->price,
        'cleaningFee' => (int) $space->cleaning_fee,
        'servicePct' => (float) config('spacerent.service_fee_percent', 5),
        'minDuration' => max(1, (int) $space->min_duration),
        'maxDuration' => $space->effectiveMaxDuration(),
        'openDays' => $space->openDays(),
        'maxAdvance' => min(max(1, (int) $space->max_advance_days), 400),
        'old' => ['date' => old('date'), 'start' => old('start_time'), 'duration' => old('duration')],
    ];
    $needsContact = $canBook && (! $user->phone || $errors->hasAny(['name', 'email', 'phone']));
@endphp
<div id="pesan" data-booking-widget data-config="{{ json_encode($cfg) }}" class="card scroll-mt-24 p-5 shadow-lift sm:p-6">
    <div class="flex items-end justify-between gap-3">
        <p><span class="text-2xl font-semibold">{{ $space->formatted_price }}</span> <span class="text-zinc-600">per {{ $space->unit_label }}</span></p>
        @if ($space->hasRating())
            <p class="flex items-center gap-1 text-sm"><i data-lucide="star" class="h-3.5 w-3.5 fill-amber-400 text-amber-400"></i><span class="font-semibold">{{ number_format($space->rating, 1) }}</span><span class="text-zinc-600">({{ $space->rating_count }})</span></p>
        @endif
    </div>

    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900" role="alert">
            <ul class="space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('booking.store', $space) }}" class="mt-5" data-once>
        @csrf
        <input type="hidden" name="date" data-role="date-input" value="{{ old('date') }}">
        <input type="hidden" name="start_time" data-role="start-input" value="{{ old('start_time') }}">
        <input type="hidden" name="duration" data-role="duration" value="{{ old('duration', max(1, (int) $space->min_duration)) }}">

        {{-- Kalender --}}
        <div>
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Pilih tanggal</h3>
                <div class="flex items-center gap-1">
                    <button type="button" data-role="prev" class="btn btn-ghost !p-2 disabled:opacity-30" aria-label="Bulan sebelumnya"><i data-lucide="chevron-left" class="h-4 w-4"></i></button>
                    <span data-role="month-label" class="min-w-28 text-center text-sm font-medium" aria-live="polite"></span>
                    <button type="button" data-role="next" class="btn btn-ghost !p-2 disabled:opacity-30" aria-label="Bulan berikutnya"><i data-lucide="chevron-right" class="h-4 w-4"></i></button>
                </div>
            </div>
            <div class="grid grid-cols-7 pb-1 text-center text-xs font-medium text-zinc-600">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $d)<span>{{ $d }}</span>@endforeach
            </div>
            <div data-role="calendar" class="grid grid-cols-7 gap-y-1"></div>
        </div>

        {{-- Jam mulai (ruangan per jam) --}}
        <div data-role="slots-wrap" class="mt-5" hidden>
            <h3 class="mb-2 text-sm font-semibold">Jam mulai</h3>
            <div data-role="slots" class="grid grid-cols-3 gap-2 sm:grid-cols-4"></div>
            <p class="help">Jam operasional {{ $space->open_time }} - {{ $space->close_time }}. Jam abu-abu sudah terisi atau tidak tersedia.</p>
        </div>

        {{-- Durasi --}}
        <div class="mt-5 flex items-center justify-between rounded-xl border border-zinc-300 px-4 py-3">
            <div>
                <p class="text-sm font-semibold">Durasi</p>
                <p class="text-xs text-zinc-600" data-role="dur-label"></p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" data-role="dur-minus" class="flex h-8 w-8 items-center justify-center rounded-full border border-zinc-300 hover:border-zinc-900 disabled:opacity-30" aria-label="Kurangi durasi"><i data-lucide="minus" class="h-4 w-4"></i></button>
                <button type="button" data-role="dur-plus" class="flex h-8 w-8 items-center justify-center rounded-full border border-zinc-300 hover:border-zinc-900 disabled:opacity-30" aria-label="Tambah durasi"><i data-lucide="plus" class="h-4 w-4"></i></button>
            </div>
        </div>

        <div class="mt-3">
            <label for="guests" class="label">Jumlah peserta</label>
            <input id="guests" type="number" name="guests" value="{{ old('guests', 1) }}" min="1" max="{{ $space->capacity }}" required class="input">
            <p class="help">Kapasitas maksimal {{ $space->capacity }} orang.</p>
        </div>

        @if ($canBook)
            <details class="mt-3 rounded-xl border border-zinc-300" @if ($needsContact) open @endif>
                <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold [&::-webkit-details-marker]:hidden">Data pemesan dan catatan</summary>
                <div class="space-y-3 border-t border-zinc-200 p-4">
                    <div><label for="name" class="label">Nama</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required class="input"></div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label for="email" class="label">Email</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="input"></div>
                        <div><label for="phone" class="label">WhatsApp</label><input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" required placeholder="0812..." class="input"></div>
                    </div>
                    <div><label for="company" class="label">Perusahaan (opsional)</label><input id="company" name="company" value="{{ old('company') }}" class="input"></div>
                    <div><label for="notes" class="label">Catatan untuk pemilik</label><textarea id="notes" name="notes" rows="2" maxlength="500" class="input">{{ old('notes') }}</textarea></div>
                </div>
            </details>
        @endif

        <div class="mt-5 rounded-xl bg-zinc-50 p-4">
            <p data-role="summary" class="mb-3 text-sm font-medium text-zinc-900" aria-live="polite"></p>
            <div data-role="price"></div>
        </div>

        @if ($canBook)
            <label class="mt-4 flex items-start gap-2.5 text-sm text-zinc-700">
                <input type="checkbox" name="agree" value="1" required class="mt-0.5 h-4 w-4 rounded border-zinc-300 accent-brand-600" @checked(old('agree'))>
                <span>Saya setuju dengan <a href="{{ route('terms') }}" class="font-medium underline underline-offset-2" target="_blank" rel="noopener">syarat</a> dan kebijakan pembatalan <strong>{{ \App\Support\RefundPolicy::label($space->cancellation_policy) }}</strong>.</span>
            </label>
            <button type="submit" data-role="submit" class="btn btn-primary btn-lg mt-4 w-full" disabled data-loading="Memproses...">{{ $space->instant_book ? 'Pesan sekarang' : 'Ajukan permintaan' }}</button>
            <p class="mt-2 text-center text-xs text-zinc-600">{{ $space->instant_book ? 'Anda belum dikenai biaya. Slot ditahan '.config('midtrans.expiry_minutes', 30).' menit untuk pembayaran.' : 'Pemilik menjawab dalam '.config('spacerent.approval_hours', 24).' jam. Bayar setelah disetujui.' }}</p>
        @elseif (! $user)
            <button type="submit" data-role="submit" class="hidden" disabled></button>
            <a href="{{ route('login', ['redirect' => url()->current()]) }}" class="btn btn-primary btn-lg mt-4 w-full">Masuk untuk memesan</a>
            <p class="mt-2 text-center text-xs text-zinc-600">Belum punya akun? <a href="{{ route('register') }}" class="font-semibold underline underline-offset-2">Daftar gratis</a></p>
        @else
            <button type="submit" data-role="submit" class="hidden" disabled></button>
            <p class="mt-4 rounded-xl bg-zinc-100 p-4 text-sm text-zinc-700">Hanya akun pembeli yang dapat memesan. Anda masuk sebagai {{ $user->isSeller() ? 'penjual' : 'admin' }}.</p>
        @endif
    </form>
</div>
