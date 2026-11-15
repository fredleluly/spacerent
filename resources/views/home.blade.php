@extends('layouts.app')

@section('title', 'SpaceRent - Sewa ruang kerja dan event space di Jakarta')

@section('content')
@php
    $active = collect($filters)->except('sort')->filter(fn ($v) => $v !== null && $v !== '');
    $hero = $heroSpaces ?? collect();
    $catUrl = fn ($slug) => route('home', array_filter(array_merge($filters, ['category' => $slug]))).'#ruangan';
@endphp

{{-- Hero --}}
<section class="container-page grid items-center gap-10 pb-10 pt-10 lg:grid-cols-[1.05fr_1fr] lg:pt-14">
    <div>
        <h1 class="max-w-xl text-4xl font-semibold leading-[1.08] tracking-tight text-zinc-900 md:text-5xl lg:text-6xl">Ruang kerja yang pas untuk hari Anda.</h1>
        <p class="mt-5 max-w-lg text-lg leading-relaxed text-zinc-600">Lihat jadwal kosong, pilih jam, dan bayar online. Meeting room, studio, dan event space di Jakarta.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="#ruangan" class="btn btn-primary btn-lg">Cari ruangan</a>
            <a href="{{ route('register', ['role' => 'seller']) }}" class="btn btn-outline btn-lg">Daftarkan ruangan</a>
        </div>
    </div>
    <div class="grid grid-cols-5 grid-rows-2 gap-3" aria-hidden="true">
        @foreach ([[0, 'col-span-3 row-span-2 aspect-[3/4]'], [1, 'col-span-2 aspect-square'], [2, 'col-span-2 aspect-[4/3]']] as [$i, $cls])
            <div class="{{ $cls }} overflow-hidden rounded-2xl bg-zinc-100">
                @if ($hero->get($i))
                    <img src="{{ $hero[$i]->image_url }}" alt="" class="h-full w-full object-cover" @if($i === 0) fetchpriority="high" @endif>
                @endif
            </div>
        @endforeach
    </div>
</section>

{{-- Pencarian --}}
<section class="container-page">
    <form method="GET" action="{{ route('home') }}#ruangan" class="card grid gap-3 p-3 shadow-soft md:grid-cols-[1.5fr_1fr_1fr_auto] md:items-end md:p-4">
        <label class="block"><span class="label">Cari</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama ruangan atau kawasan" class="input">
        </label>
        <label class="block"><span class="label">Wilayah</span>
            <select name="area" class="input">
                <option value="">Semua wilayah</option>
                @foreach (\App\Models\Space::AREAS as $a)<option value="{{ $a }}" @selected(($filters['area'] ?? '') === $a)>{{ $a }}</option>@endforeach
            </select>
        </label>
        <label class="block"><span class="label">Tanggal</span>
            <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" min="{{ today()->toDateString() }}" class="input">
        </label>
        <button class="btn btn-dark h-[46px]"><i data-lucide="search" class="h-4 w-4"></i> Cari</button>
        @if (! empty($filters['category']))<input type="hidden" name="category" value="{{ $filters['category'] }}">@endif
    </form>
</section>

{{-- Peta --}}
@include('partials.space-map')

{{-- Daftar --}}
<section id="ruangan" class="container-page scroll-mt-20 pt-10">
    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
        <a href="{{ $catUrl(null) }}" class="chip" @if (empty($filters['category'])) aria-current="true" @endif>Semua</a>
        @foreach (\App\Models\Space::CATEGORIES as $slug => $c)
            <a href="{{ $catUrl($slug) }}" class="chip" @if (($filters['category'] ?? '') === $slug) aria-current="true" @endif><i data-lucide="{{ $c['icon'] }}" class="h-4 w-4"></i>{{ $c['label'] }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('home') }}#ruangan" class="mt-4 flex flex-wrap items-center gap-2">
        @foreach (['q', 'area', 'category', 'date', 'capacity'] as $keep)
            @if (! empty($filters[$keep]))<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif
        @endforeach
        <select name="sort" class="input !w-auto" aria-label="Urutkan" onchange="this.form.submit()">
            @foreach (\App\Models\Space::SORTS as $k => $label)<option value="{{ $k }}" @selected(($filters['sort'] ?? 'recommended') === $k)>{{ $label }}</option>@endforeach
        </select>
        <select name="unit" class="input !w-auto" aria-label="Satuan sewa" onchange="this.form.submit()">
            <option value="">Semua satuan</option>
            <option value="jam" @selected(($filters['unit'] ?? '') === 'jam')>Per jam</option>
            <option value="hari" @selected(($filters['unit'] ?? '') === 'hari')>Per hari</option>
            <option value="bulan" @selected(($filters['unit'] ?? '') === 'bulan')>Per bulan</option>
        </select>
        <select name="min_rating" class="input !w-auto" aria-label="Rating minimum" onchange="this.form.submit()">
            <option value="">Semua rating</option>
            @foreach ([4.5, 4, 3] as $r)<option value="{{ $r }}" @selected((string) ($filters['min_rating'] ?? '') === (string) $r)>Rating {{ $r }}+</option>@endforeach
        </select>
        <select name="capacity" class="input !w-auto" aria-label="Kapasitas" onchange="this.form.submit()">
            <option value="">Semua kapasitas</option>
            @foreach (\App\Models\Space::CAPACITIES as $k => $label)<option value="{{ $k }}" @selected(($filters['capacity'] ?? '') === $k)>{{ $label }}</option>@endforeach
        </select>
        <p class="ml-auto text-sm text-zinc-600">{{ $spaces->total() }} ruangan</p>
        @if ($active->isNotEmpty())
            <a href="{{ route('home') }}#ruangan" class="text-sm font-semibold text-brand-700 underline underline-offset-4">Reset filter</a>
        @endif
    </form>

    @if ($spaces->isEmpty())
        <div class="mx-auto mt-14 max-w-md rounded-2xl border border-dashed border-zinc-300 p-10 text-center">
            <i data-lucide="search-x" class="mx-auto h-10 w-10 text-zinc-500"></i>
            <h2 class="mt-4 text-lg font-semibold">Belum ada ruangan yang cocok</h2>
            <p class="mt-1 text-sm text-zinc-600">Coba ganti tanggal, longgarkan filter, atau lihat semua ruangan.</p>
            <a href="{{ route('home') }}#ruangan" class="btn btn-dark mt-5">Lihat semua ruangan</a>
        </div>
    @else
        <div class="mt-8 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($spaces as $space)
                @include('partials.space-card', ['space' => $space])
            @endforeach
        </div>
        <div class="mt-12">{{ $spaces->links() }}</div>
    @endif
</section>

{{-- Cara kerja --}}
<section class="container-page mt-20 grid items-center gap-10 lg:grid-cols-2">
    <div class="aspect-[5/4] overflow-hidden rounded-2xl bg-zinc-100">
        @if ($hero->get(1))<img src="{{ $hero[1]->image_url }}" alt="" loading="lazy" class="h-full w-full object-cover">@endif
    </div>
    <div>
        <h2 class="text-3xl font-semibold tracking-tight">Pesan tanpa tebak-tebakan</h2>
        <ul class="mt-8 space-y-7">
            <li class="flex gap-4"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"><i data-lucide="calendar-days" class="h-5 w-5"></i></span><div><h3 class="font-semibold">Pilih jadwal dari kalender</h3><p class="mt-1 text-sm leading-relaxed text-zinc-600">Jam yang sudah terisi langsung abu-abu. Tidak perlu bertanya apakah ruangan masih kosong.</p></div></li>
            <li class="flex gap-4"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"><i data-lucide="wallet" class="h-5 w-5"></i></span><div><h3 class="font-semibold">Bayar dengan harga yang jelas</h3><p class="mt-1 text-sm leading-relaxed text-zinc-600">Sewa, biaya kebersihan, dan biaya layanan tampil sebelum Anda membayar. Slot ditahan selama {{ config('midtrans.expiry_minutes', 30) }} menit.</p></div></li>
            <li class="flex gap-4"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"><i data-lucide="undo-2" class="h-5 w-5"></i></span><div><h3 class="font-semibold">Batal dan refund sesuai kebijakan</h3><p class="mt-1 text-sm leading-relaxed text-zinc-600">Setiap ruangan menampilkan kebijakan batalnya. Jika pemilik membatalkan, Anda menerima refund penuh.</p></div></li>
        </ul>
    </div>
</section>

@include('partials.sdgs-section')

<section class="container-page mt-20">
    <div class="flex flex-col items-start justify-between gap-6 rounded-2xl bg-brand-50 p-8 md:flex-row md:items-center md:p-12">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight md:text-3xl">Punya ruangan yang jarang terpakai?</h2>
            <p class="mt-2 max-w-xl text-zinc-700">Pasang di SpaceRent, atur jam buka dan kebijakan batal sendiri, lalu terima pesanan dari penyewa terverifikasi.</p>
        </div>
        <a href="{{ route('register', ['role' => 'seller']) }}" class="btn btn-dark btn-lg shrink-0">Jadi mitra</a>
    </div>
</section>
@endsection
