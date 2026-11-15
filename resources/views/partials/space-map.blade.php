{{-- Peta penjelajah ruangan (Leaflet + OpenStreetMap). Data dari route spaces.map. Logika di resources/js/map.js. --}}
<section id="peta" class="container-page mt-12 scroll-mt-20" data-space-map data-url="{{ route('spaces.map') }}" aria-labelledby="peta-title">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 id="peta-title" class="text-2xl font-semibold tracking-tight">Cari ruangan di peta</h2>
            <p class="mt-1 text-sm text-zinc-600">Lihat lokasi tiap ruangan, atau temukan yang paling dekat dengan Anda.</p>
        </div>
        <p class="text-xs text-zinc-600" data-status role="status" aria-live="polite"></p>
    </div>

    <form class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto_auto]" role="search" aria-label="Cari di peta">
        <label class="relative block">
            <span class="sr-only">Cari kawasan atau nama ruangan</span>
            <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500"></i>
            <input type="search" data-q placeholder="Cari kawasan atau nama (mis. Kemang, SCBD)" class="input !pl-10" autocomplete="off">
        </label>
        <label class="block">
            <span class="sr-only">Kategori</span>
            <select data-cat class="input sm:!w-auto" aria-label="Kategori">
                <option value="">Semua kategori</option>
                @foreach (\App\Models\Space::CATEGORIES as $slug => $c)<option value="{{ $slug }}">{{ $c['label'] }}</option>@endforeach
            </select>
        </label>
        <button type="button" data-near class="btn btn-dark" aria-pressed="false"><i data-lucide="map-pin" class="h-4 w-4"></i> Dekat saya</button>
    </form>

    <div class="mt-4 grid gap-4 lg:grid-cols-[1.7fr_1fr]">
        <div data-map class="sr-map h-[360px] overflow-hidden rounded-2xl border border-zinc-200 sm:h-[460px] lg:h-[540px]" role="application" aria-label="Peta lokasi ruangan"></div>
        <ul data-list class="max-h-[320px] space-y-1 overflow-y-auto rounded-2xl border border-zinc-200 p-2 lg:max-h-[540px]" aria-label="Daftar ruangan di peta"></ul>
    </div>
    <p class="mt-2 text-xs text-zinc-500">Lokasi Anda hanya dipakai di peramban untuk mengurutkan jarak dan tidak disimpan di server.</p>
</section>
