@extends('layouts.app')

@section('content')
{{-- Hero --}}
<section class="relative overflow-hidden bg-brand-900">
    <img src="https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1920&q=70" alt="" class="absolute inset-0 h-full w-full object-cover opacity-30" onerror="this.style.display='none'">
    <div class="absolute inset-0 bg-gradient-to-b from-slate-900/60 via-brand-900/80 to-brand-900"></div>

    <div class="relative mx-auto max-w-6xl px-4 pb-16 pt-14 text-center md:pt-20">
        <span class="inline-block rounded-full border border-sky-400/40 bg-sky-400/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-wider text-sky-300">
            Penyedia Space &amp; Ruang Kerja #1 di Jakarta
        </span>
        <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-extrabold leading-[1.05] tracking-tight text-white md:text-6xl">
            Sewa Ruang Kerja &amp; Event Space Tanpa Ribet &amp; Transparan
        </h1>
        <p class="mx-auto mt-5 max-w-2xl text-base text-slate-200 md:text-lg">
            Pilihan lengkap Meeting Room, Private Office, Coworking, dan Event Venue siap pakai dengan fasilitas lengkap di seluruh titik strategis Jakarta.
        </p>

        {{-- Filter --}}
        <form method="GET" action="{{ route('home') }}#ruangan" class="mx-auto mt-10 grid max-w-4xl gap-3 rounded-2xl bg-white p-4 text-left shadow-2xl md:grid-cols-[1.1fr_1.1fr_1.1fr_auto] md:items-end">
            @if (!empty($filters['category']))
                <input type="hidden" name="category" value="{{ $filters['category'] }}">
            @endif
            <label class="block">
                <span class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-500"><i data-lucide="search" class="h-3.5 w-3.5 text-brand-600"></i>Cari Nama / Kata Kunci</span>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Contoh: SCBD, Meeting, Podcast"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </label>
            <label class="block">
                <span class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-500"><i data-lucide="map-pin" class="h-3.5 w-3.5 text-brand-600"></i>Pilih Wilayah</span>
                <select name="area" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    <option value="">Semua Lokasi Jakarta</option>
                    @foreach (\App\Models\Space::AREAS as $area)
                        <option value="{{ $area }}" @selected(($filters['area'] ?? '') === $area)>{{ $area }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-500"><i data-lucide="users" class="h-3.5 w-3.5 text-brand-600"></i>Kapasitas Orang</span>
                <select name="capacity" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    <option value="">Semua Kapasitas</option>
                    @foreach (\App\Models\Space::CAPACITIES as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['capacity'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand-600/30 transition hover:bg-brand-700">
                <i data-lucide="filter" class="h-4 w-4"></i> Terapkan Filter
            </button>
        </form>

        {{-- Stats --}}
        <div class="mx-auto mt-10 grid max-w-4xl grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ([['50+', 'Pilihan Lokasi Space'], ['10.000+', 'Pelanggan & Korporasi'], ['100%', 'Siap Pakai & Siap Rapat'], ['4.9 / 5', 'Rating Kepuasan']] as [$n, $l])
                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur">
                    <p class="text-2xl font-extrabold text-white">{{ $n }}</p>
                    <p class="mt-0.5 text-xs text-slate-300">{{ $l }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Categories --}}
<section class="bg-white py-12">
    <div class="mx-auto max-w-6xl px-4">
        <p class="text-center text-[11px] font-bold uppercase tracking-widest text-brand-600">Kategori Layanan</p>
        <h2 class="mt-2 text-center text-2xl font-extrabold text-ink md:text-3xl">Pilih Jenis Ruangan Sesuai Kebutuhan Anda</h2>

        @php
            $active = $filters['category'] ?? null;
            $keep = collect($filters)->except('category')->all();
        @endphp
        <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
            <a href="{{ route('home', $keep) }}#ruangan" class="flex flex-col items-center gap-2 rounded-xl border px-3 py-4 text-xs font-semibold transition {{ !$active ? 'border-brand-600 bg-brand-600 text-white shadow-lg shadow-brand-600/30' : 'border-slate-200 bg-white text-slate-600 hover:border-brand-500 hover:text-brand-600' }}">
                <i data-lucide="layout-grid" class="h-5 w-5"></i>Semua Ruangan
            </a>
            @foreach (\App\Models\Space::CATEGORIES as $slug => $c)
                <a href="{{ route('home', $keep + ['category' => $slug]) }}#ruangan" class="flex flex-col items-center gap-2 rounded-xl border px-3 py-4 text-xs font-semibold transition {{ $active === $slug ? 'border-brand-600 bg-brand-600 text-white shadow-lg shadow-brand-600/30' : 'border-slate-200 bg-white text-slate-600 hover:border-brand-500 hover:text-brand-600' }}">
                    <i data-lucide="{{ $c['icon'] }}" class="h-5 w-5"></i>{{ $c['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Listing --}}
<section id="ruangan" class="scroll-mt-20 bg-slate-50 py-14">
    <div class="mx-auto max-w-6xl px-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-widest text-brand-600">Armada Ruangan &amp; Venue</p>
                <h2 class="mt-2 text-2xl font-extrabold text-ink md:text-3xl">Pilihan Unit Ruangan Siap Sewa</h2>
            </div>
            <p class="text-sm text-slate-500">Menampilkan <b class="text-ink">{{ $spaces->count() }}</b> ruangan tersedia</p>
        </div>

        @if ($spaces->isEmpty())
            <div class="mt-10 rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center">
                <i data-lucide="search-x" class="mx-auto h-10 w-10 text-slate-300"></i>
                <p class="mt-4 font-bold text-ink">Ruangan tidak ditemukan</p>
                <p class="mt-1 text-sm text-slate-500">Coba ubah kata kunci atau reset filter Anda.</p>
                <a href="{{ route('home') }}#ruangan" class="mt-5 inline-block rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-700">Reset Filter</a>
            </div>
        @else
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($spaces as $space)
                    @include('partials.space-card', ['space' => $space])
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- Why us --}}
<section class="bg-white py-16">
    <div class="mx-auto max-w-6xl px-4">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-brand-600">Kenapa SpaceRent</p>
            <h2 class="mt-2 text-2xl font-extrabold md:text-3xl">Semua Kebutuhan Ruang, Satu Tempat</h2>
        </div>
        <div class="mt-10 grid gap-6 md:grid-cols-4">
            @foreach ([
                ['receipt', 'Harga Transparan', 'Tanpa biaya tersembunyi. Semua fasilitas tertera jelas di setiap ruangan.'],
                ['zap', 'Siap Pakai', 'Furnitur, WiFi, dan perangkat presentasi sudah lengkap dan terawat.'],
                ['map-pinned', 'Lokasi Strategis', 'Titik premium di SCBD, Thamrin, Kuningan, Kemang, dan sekitarnya.'],
                ['headset', 'Layanan 24/7', 'Tim kami siap membantu reservasi lewat WhatsApp kapan saja.'],
            ] as [$icon, $title, $desc])
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
                    <h3 class="mt-4 font-bold">{{ $title }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Steps --}}
<section class="bg-brand-900 py-16 text-white">
    <div class="mx-auto max-w-6xl px-4">
        <h2 class="text-center text-2xl font-extrabold md:text-3xl">Cara Memesan</h2>
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            @foreach ([['1', 'Pilih Ruangan', 'Gunakan filter wilayah, kapasitas, dan kategori untuk menemukan space yang pas.'], ['2', 'Isi Form Booking', 'Tentukan tanggal, durasi, dan jumlah peserta. Estimasi biaya muncul otomatis.'], ['3', 'Konfirmasi via WhatsApp', 'Kirim kode booking ke CS kami dan selesaikan pembayaran.']] as [$n, $t, $d])
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-sky-400 font-extrabold text-brand-900">{{ $n }}</span>
                    <h3 class="mt-4 text-lg font-bold">{{ $t }}</h3>
                    <p class="mt-1.5 text-sm text-slate-300">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
