@php
    $editing = $space->exists;
    $currentImageUrl = $editing && str_starts_with($space->image, 'http') ? $space->image : '';
    $oldDays = old('open_days', $space->openDays());
    $section = 'card space-y-5 p-6';
@endphp
<a href="{{ $cancelUrl }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-700 hover:text-zinc-900"><i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali</a>

<form method="POST" enctype="multipart/form-data" action="{{ $action }}" class="max-w-4xl space-y-6" data-once>
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="alert">
            <p class="font-semibold">Periksa kembali isian Anda</p>
            <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="{{ $section }}">
        <h2 class="font-semibold">Informasi dasar</h2>
        <div class="grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2"><label class="label" for="name">Nama ruangan</label><input id="name" type="text" name="name" value="{{ old('name', $space->name) }}" required class="input"></div>
            <div><label class="label" for="category">Kategori</label>
                <select id="category" name="category" required class="input">@foreach (\App\Models\Space::CATEGORIES as $slug => $c)<option value="{{ $slug }}" @selected(old('category', $space->category) === $slug)>{{ $c['label'] }}</option>@endforeach</select></div>
            <div><label class="label" for="area">Wilayah</label>
                <select id="area" name="area" required class="input">@foreach (\App\Models\Space::AREAS as $a)<option value="{{ $a }}" @selected(old('area', $space->area) === $a)>{{ $a }}</option>@endforeach</select></div>
            <div><label class="label" for="district">Kawasan</label><input id="district" type="text" name="district" value="{{ old('district', $space->district) }}" required placeholder="SCBD, Kemang, Thamrin" class="input"></div>
            <div><label class="label" for="capacity">Kapasitas (orang)</label><input id="capacity" type="number" name="capacity" min="1" value="{{ old('capacity', $space->capacity) }}" required class="input"></div>
            <div class="md:col-span-2"><label class="label" for="description">Deskripsi</label><textarea id="description" name="description" rows="4" required class="input">{{ old('description', $space->description) }}</textarea></div>
            <div class="md:col-span-2"><label class="label" for="facilities">Fasilitas</label><textarea id="facilities" name="facilities" rows="4" class="input">{{ old('facilities', implode("\n", $space->facilities ?? [])) }}</textarea><p class="help">Satu fasilitas per baris.</p></div>
        </div>
    </section>

    <section class="{{ $section }}">
        <h2 class="font-semibold">Harga dan biaya</h2>
        <div class="grid gap-5 md:grid-cols-3">
            <div><label class="label" for="price">Harga (Rp)</label><input id="price" type="number" name="price" min="0" value="{{ old('price', $space->price) }}" required class="input"></div>
            <div><label class="label" for="price_unit">Satuan</label>
                <select id="price_unit" name="price_unit" required class="input">@foreach (['jam', 'hari', 'bulan'] as $u)<option value="{{ $u }}" @selected(old('price_unit', $space->price_unit) === $u)>per {{ $u }}</option>@endforeach</select></div>
            <div><label class="label" for="cleaning_fee">Biaya kebersihan (Rp)</label><input id="cleaning_fee" type="number" name="cleaning_fee" min="0" value="{{ old('cleaning_fee', $space->cleaning_fee) }}" required class="input"><p class="help">Dibayar sekali per booking.</p></div>
        </div>
        <p class="help">Pembeli juga membayar biaya layanan SpaceRent {{ config('spacerent.service_fee_percent') }}% di atas harga ini.</p>
    </section>

    <section class="{{ $section }}">
        <h2 class="font-semibold">Jadwal dan aturan pemesanan</h2>
        <div class="grid gap-5 md:grid-cols-2">
            <div><label class="label" for="open_time">Jam buka</label><input id="open_time" type="time" name="open_time" value="{{ old('open_time', $space->open_time) }}" required class="input"></div>
            <div><label class="label" for="close_time">Jam tutup</label><input id="close_time" type="time" name="close_time" value="{{ old('close_time', $space->close_time) }}" required class="input"><p class="help">Berlaku untuk ruangan per jam.</p></div>
        </div>
        <fieldset>
            <legend class="label">Hari buka</legend>
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Models\Space::DAYS as $d => $name)
                    <label class="cursor-pointer"><input type="checkbox" name="open_days[]" value="{{ $d }}" class="peer sr-only" @checked(in_array($d, array_map('intval', (array) $oldDays), true))><span class="chip peer-checked:border-zinc-900 peer-checked:bg-zinc-900 peer-checked:text-white peer-focus-visible:outline-2">{{ $name }}</span></label>
                @endforeach
            </div>
        </fieldset>
        <div class="grid gap-5 md:grid-cols-3">
            <div><label class="label" for="min_duration">Durasi minimum</label><input id="min_duration" type="number" name="min_duration" min="1" value="{{ old('min_duration', $space->min_duration) }}" required class="input"><p class="help">Dalam satuan harga.</p></div>
            <div><label class="label" for="max_duration">Durasi maksimum</label><input id="max_duration" type="number" name="max_duration" min="1" value="{{ old('max_duration', $space->max_duration) }}" class="input"><p class="help">Kosong = bawaan (12 jam, 31 hari, 12 bulan).</p></div>
            <div><label class="label" for="buffer_minutes">Jeda antar pemesan (menit)</label><input id="buffer_minutes" type="number" name="buffer_minutes" min="0" value="{{ old('buffer_minutes', $space->buffer_minutes) }}" required class="input"><p class="help">Waktu bersih-bersih, untuk ruangan per jam.</p></div>
            <div><label class="label" for="lead_time_hours">Pesan minimal (jam sebelumnya)</label><input id="lead_time_hours" type="number" name="lead_time_hours" min="0" value="{{ old('lead_time_hours', $space->lead_time_hours) }}" required class="input"></div>
            <div><label class="label" for="max_advance_days">Bisa dipesan sampai (hari ke depan)</label><input id="max_advance_days" type="number" name="max_advance_days" min="1" max="730" value="{{ old('max_advance_days', $space->max_advance_days) }}" required class="input"></div>
            <div><label class="label" for="cancellation_policy">Kebijakan pembatalan</label>
                <select id="cancellation_policy" name="cancellation_policy" required class="input">@foreach (\App\Support\RefundPolicy::POLICIES as $k => $p)<option value="{{ $k }}" @selected(old('cancellation_policy', $space->cancellation_policy) === $k)>{{ $p['label'] }}</option>@endforeach</select></div>
        </div>
        <div class="grid gap-3">
            <input type="hidden" name="instant_book" value="0">
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="instant_book" value="1" class="mt-0.5 h-4 w-4 accent-brand-600" @checked(old('instant_book', $space->instant_book))><span><strong>Instant Book</strong><br><span class="text-zinc-600">Pembeli langsung memesan tanpa menunggu persetujuan. Matikan jika Anda ingin menyetujui tiap permintaan.</span></span></label>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_available" value="1" class="mt-0.5 h-4 w-4 accent-brand-600" @checked(old('is_available', $editing ? $space->is_available : true))><span><strong>Terima pesanan baru</strong><br><span class="text-zinc-600">Matikan untuk menutup sementara. Booking yang sudah ada tetap berlaku.</span></span></label>
        </div>
    </section>

    <section class="{{ $section }}">
        <h2 class="font-semibold">Aturan dan akses</h2>
        <div><label class="label" for="rules">Aturan ruangan</label><textarea id="rules" name="rules" rows="3" class="input" placeholder="Dilarang merokok, maksimal suara, kebersihan setelah pakai...">{{ old('rules', $space->rules) }}</textarea></div>
        <div><label class="label" for="checkin_info">Info check-in</label><textarea id="checkin_info" name="checkin_info" rows="3" class="input" placeholder="Lokasi lobi, cara masuk, kontak petugas...">{{ old('checkin_info', $space->checkin_info) }}</textarea><p class="help">Hanya dikirim ke pembeli setelah booking terkonfirmasi.</p></div>
    </section>

    <section class="{{ $section }}">
        <h2 class="font-semibold">Lokasi di peta</h2>
        @include('partials.location-picker', ['space' => $space])
    </section>

    <section class="{{ $section }}">
        <h2 class="font-semibold">Foto</h2>
        @if ($editing)<img src="{{ $space->image_url }}" alt="" class="h-28 w-44 rounded-xl bg-zinc-100 object-cover">@endif
        <div class="grid gap-5 md:grid-cols-2">
            <div><label class="label" for="image_file">Foto sampul (unggah)</label><input id="image_file" type="file" name="image_file" accept="image/*" class="input"><p class="help">JPG, PNG, atau WebP, maks. 4 MB.</p></div>
            <div><label class="label" for="image_url">atau URL foto sampul</label><input id="image_url" type="url" name="image_url" value="{{ old('image_url', $currentImageUrl) }}" placeholder="https://..." class="input"></div>
        </div>
        @if ($editing)<p class="help">Kosongkan keduanya untuk mempertahankan foto sampul saat ini.</p>@endif
        <div>
            <label class="label" for="gallery_files">Foto galeri (maks. 6 per unggahan, total 8)</label>
            <input id="gallery_files" type="file" name="gallery_files[]" accept="image/jpeg,image/png,image/webp" multiple class="input">
            @if ($editing && ! empty($space->gallery))
                <div class="mt-3 flex flex-wrap gap-3">
                    @foreach ($space->gallery as $g)
                        <label class="relative cursor-pointer">
                            <img src="{{ str_starts_with($g, 'http') ? $g : asset('storage/'.$g) }}" alt="" class="h-20 w-28 rounded-xl object-cover">
                            <span class="mt-1 flex items-center gap-1.5 text-xs text-zinc-700"><input type="checkbox" name="remove_gallery[]" value="{{ $g }}" class="accent-brand-600"> Hapus</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="flex gap-3">
        <button type="submit" class="btn btn-primary btn-lg" data-loading="Menyimpan...">{{ $editing ? 'Simpan perubahan' : 'Tambah ruangan' }}</button>
        <a href="{{ $cancelUrl }}" class="btn btn-outline btn-lg">Batal</a>
    </div>
</form>
