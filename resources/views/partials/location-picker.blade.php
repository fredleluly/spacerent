{{-- Pemilih lokasi ruangan untuk penjual/admin. Mengisi input latitude, longitude, address_line. Logika di resources/js/map.js. --}}
@php
    $lat = old('latitude', $space->latitude);
    $lng = old('longitude', $space->longitude);
@endphp
<div data-location-picker class="space-y-4">
    <div>
        <label class="label" for="loc-search">Cari alamat atau nama gedung</label>
        <div class="flex gap-2">
            <input id="loc-search" type="text" data-search class="input" placeholder="Mis. Menara BCA Jakarta, atau Jl. Kemang Raya" autocomplete="off">
            <button type="button" data-search-btn class="btn btn-dark shrink-0"><i data-lucide="search" class="h-4 w-4"></i> Cari</button>
            <button type="button" data-locate class="btn btn-outline shrink-0" title="Pakai lokasi saya sekarang"><i data-lucide="map-pin" class="h-4 w-4"></i><span class="hidden sm:inline">Lokasi saya</span></button>
        </div>
        <ul data-results class="mt-2 divide-y divide-zinc-100 rounded-xl border border-zinc-200 empty:hidden"></ul>
    </div>

    <div data-map class="sr-map h-[320px] overflow-hidden rounded-2xl border border-zinc-200" role="application" aria-label="Peta untuk menetapkan lokasi ruangan"></div>
    <p data-msg class="mt-2 text-xs text-zinc-600" role="status" aria-live="polite"></p>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label" for="latitude">Lintang (latitude)</label>
            <input id="latitude" type="text" inputmode="decimal" name="latitude" data-lat value="{{ $lat }}" class="input" placeholder="-6.2250" aria-invalid="{{ $errors->has('latitude') ? 'true' : 'false' }}">
            @error('latitude')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="longitude">Bujur (longitude)</label>
            <input id="longitude" type="text" inputmode="decimal" name="longitude" data-lng value="{{ $lng }}" class="input" placeholder="106.8090" aria-invalid="{{ $errors->has('longitude') ? 'true' : 'false' }}">
            @error('longitude')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="label" for="address_line">Alamat lengkap (tampil di peta dan chatbot)</label>
            <input id="address_line" type="text" name="address_line" data-address value="{{ old('address_line', $space->address_line) }}" maxlength="255" class="input" placeholder="Jl. ..., Kelurahan, Kecamatan, Jakarta">
            @error('address_line')<p class="error">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="flex items-center justify-between gap-3">
        <p class="help !mt-0">Tanpa lokasi, ruangan tidak muncul di peta dan pencarian "terdekat".</p>
        <button type="button" data-clear class="text-xs font-semibold text-rose-700 underline underline-offset-4">Hapus lokasi</button>
    </div>
</div>
