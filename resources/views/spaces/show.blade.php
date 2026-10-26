@extends('layouts.app')

@section('title', $space->name.' - SpaceRent')
@section('description', \Illuminate\Support\Str::limit($space->description, 150))

@section('content')
@php
    $unitLabel = ['jam' => 'Jam', 'hari' => 'Hari', 'bulan' => 'Bulan'][$space->price_unit] ?? ucfirst($space->price_unit);
    $maxDuration = $space->price_unit === 'jam' ? 12 : 31;
@endphp
<div class="bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-8">
        <nav class="mb-5 flex items-center gap-2 text-xs text-slate-500">
            <a href="{{ route('home') }}" class="hover:text-brand-600">Beranda</a>
            <i data-lucide="chevron-right" class="h-3 w-3"></i>
            <a href="{{ route('home', ['category' => $space->category]) }}#ruangan" class="hover:text-brand-600">{{ $space->category_label }}</a>
            <i data-lucide="chevron-right" class="h-3 w-3"></i>
            <span class="font-semibold text-slate-700">{{ $space->name }}</span>
        </nav>

        <div class="grid gap-8 lg:grid-cols-[1.6fr_1fr]">
            <div>
                <div class="relative h-72 overflow-hidden rounded-2xl bg-slate-200 md:h-96">
                    <img src="{{ $space->image_url }}" alt="{{ $space->name }}" class="h-full w-full object-cover" onerror="this.style.display='none'">
                    <span class="absolute left-4 top-4 rounded-md bg-slate-900/85 px-3 py-1 text-xs font-bold text-white">{{ $space->category_label }}</span>
                </div>

                <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
                    <p class="flex items-center gap-1.5 text-sm text-slate-500"><i data-lucide="map-pin" class="h-4 w-4 text-brand-600"></i>{{ $space->location }}</p>
                    <h1 class="mt-2 text-2xl font-extrabold md:text-3xl">{{ $space->name }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-5 text-sm text-slate-600">
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="users" class="h-4 w-4"></i>Kapasitas {{ $space->capacity }} orang</span>
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="star" class="h-4 w-4 fill-amber-400 text-amber-400"></i>{{ number_format($space->rating, 1) }} / 5</span>
                    </div>
                    <p class="mt-5 leading-relaxed text-slate-600">{{ $space->description }}</p>

                    <h2 class="mt-7 text-lg font-bold">Fasilitas</h2>
                    <ul class="mt-3 grid gap-2.5 sm:grid-cols-2">
                        @foreach ($space->facilities ?? [] as $f)
                            <li class="flex items-center gap-2 text-sm text-slate-700"><i data-lucide="circle-check" class="h-4 w-4 text-emerald-500"></i>{{ $f }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Booking card --}}
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <form method="POST" action="{{ route('booking.store', $space) }}" id="booking-form" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-lg">
                    @csrf
                    <p class="text-xs text-slate-400">Harga mulai dari</p>
                    <p class="text-3xl font-extrabold text-brand-600">{{ $space->formatted_price }}<span class="text-sm font-medium text-slate-400"> / {{ $space->price_unit }}</span></p>

                    @if ($errors->any())
                        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                            <p class="font-bold">Periksa kembali isian Anda:</p>
                            <ul class="mt-1 list-inside list-disc">
                                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    @php
                        $input = 'mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
                        $label = 'block text-[11px] font-bold uppercase tracking-wide text-slate-500';
                    @endphp

                    @if ($space->is_available)
                    <div class="mt-5 space-y-3.5">
                        <label class="{{ $label }}">Nama Lengkap
                            <input type="text" name="name" value="{{ old('name') }}" required class="{{ $input }}">
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="{{ $label }}">Email
                                <input type="email" name="email" value="{{ old('email') }}" required class="{{ $input }}">
                            </label>
                            <label class="{{ $label }}">No. WhatsApp
                                <input type="tel" name="phone" value="{{ old('phone') }}" required placeholder="0812..." class="{{ $input }}">
                            </label>
                        </div>
                        <label class="{{ $label }}">Perusahaan (opsional)
                            <input type="text" name="company" value="{{ old('company') }}" class="{{ $input }}">
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="{{ $label }}">Tanggal
                                <input type="date" name="date" value="{{ old('date') }}" min="{{ now()->toDateString() }}" required class="{{ $input }}">
                            </label>
                            @if ($space->price_unit === 'jam')
                                <label class="{{ $label }}">Jam Mulai
                                    <input type="time" name="start_time" value="{{ old('start_time', '09:00') }}" required class="{{ $input }}">
                                </label>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="{{ $label }}">Durasi ({{ $unitLabel }})
                                <input type="number" name="duration" id="duration" value="{{ old('duration', 1) }}" min="1" max="{{ $maxDuration }}" required class="{{ $input }}">
                            </label>
                            <label class="{{ $label }}">Jumlah Peserta
                                <input type="number" name="guests" value="{{ old('guests', 1) }}" min="1" max="{{ $space->capacity }}" required class="{{ $input }}">
                            </label>
                        </div>
                        <label class="{{ $label }}">Catatan
                            <textarea name="notes" rows="2" class="{{ $input }}">{{ old('notes') }}</textarea>
                        </label>
                    </div>

                    <div class="mt-5 flex items-center justify-between rounded-xl bg-brand-50 px-4 py-3">
                        <span class="text-sm font-semibold text-slate-600">Estimasi Total</span>
                        <span id="estimate" class="text-lg font-extrabold text-brand-600">{{ $space->formatted_price }}</span>
                    </div>

                    <button type="submit" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-brand-600/30 transition hover:bg-brand-700">
                        <i data-lucide="calendar-check" class="h-4 w-4"></i> Pesan Sekarang
                    </button>
                    <p class="mt-3 text-center text-[11px] text-slate-400">Belum ada pembayaran. Booking dikonfirmasi oleh CS via WhatsApp.</p>
                    @else
                        <p class="mt-5 rounded-lg bg-slate-100 p-4 text-center text-sm text-slate-500">Ruangan ini sedang penuh.</p>
                    @endif
                </form>
            </aside>
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-14">
                <h2 class="text-xl font-extrabold">Ruangan Serupa</h2>
                <div class="mt-5 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $item)
                        @include('partials.space-card', ['space' => $item])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const price = {{ (int) $space->price }};
        const input = document.getElementById('duration');
        const out = document.getElementById('estimate');
        if (!input || !out) return;
        const update = () => {
            const n = Math.max(1, parseInt(input.value, 10) || 1);
            out.textContent = 'Rp ' + (price * n).toLocaleString('id-ID');
        };
        input.addEventListener('input', update);
        update();
    })();
</script>
@endpush
