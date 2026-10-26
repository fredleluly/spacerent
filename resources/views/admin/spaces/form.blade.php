@extends('admin.layout')
@php
    $editing = $space->exists;
    $input = 'mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm normal-case font-normal text-slate-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-slate-500';
    $currentImageUrl = $editing && str_starts_with($space->image, 'http') ? $space->image : '';
@endphp
@section('title', $editing ? 'Edit Ruangan' : 'Tambah Ruangan')
@section('heading', $editing ? 'Edit Ruangan' : 'Tambah Ruangan')

@section('content')
<a href="{{ route('admin.spaces.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600"><i data-lucide="arrow-left" class="h-4 w-4"></i>Kembali</a>

<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.spaces.update', $space) : route('admin.spaces.store') }}" class="max-w-4xl rounded-xl border border-slate-200 bg-white p-6">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <label class="{{ $label }} md:col-span-2">Nama Ruangan
            <input type="text" name="name" value="{{ old('name', $space->name) }}" required class="{{ $input }}">
        </label>
        <label class="{{ $label }}">Kategori
            <select name="category" required class="{{ $input }}">
                @foreach (\App\Models\Space::CATEGORIES as $slug => $c)<option value="{{ $slug }}" @selected(old('category', $space->category) === $slug)>{{ $c['label'] }}</option>@endforeach
            </select>
        </label>
        <label class="{{ $label }}">Wilayah
            <select name="area" required class="{{ $input }}">
                @foreach (\App\Models\Space::AREAS as $a)<option value="{{ $a }}" @selected(old('area', $space->area) === $a)>{{ $a }}</option>@endforeach
            </select>
        </label>
        <label class="{{ $label }}">Kawasan / Distrik
            <input type="text" name="district" value="{{ old('district', $space->district) }}" required placeholder="SCBD, Kemang, Thamrin..." class="{{ $input }}">
        </label>
        <label class="{{ $label }}">Kapasitas (orang)
            <input type="number" name="capacity" min="1" value="{{ old('capacity', $space->capacity) }}" required class="{{ $input }}">
        </label>
        <label class="{{ $label }}">Harga (Rp)
            <input type="number" name="price" min="0" value="{{ old('price', $space->price) }}" required class="{{ $input }}">
        </label>
        <label class="{{ $label }}">Satuan Harga
            <select name="price_unit" required class="{{ $input }}">
                @foreach (['jam', 'hari', 'bulan'] as $u)<option value="{{ $u }}" @selected(old('price_unit', $space->price_unit) === $u)>per {{ $u }}</option>@endforeach
            </select>
        </label>
        <label class="{{ $label }}">Rating (0-5)
            <input type="number" name="rating" step="0.1" min="0" max="5" value="{{ old('rating', $space->rating) }}" required class="{{ $input }}">
        </label>
        <label class="flex items-end gap-2 pb-2.5 text-sm font-semibold text-slate-700">
            <input type="checkbox" name="is_available" value="1" class="h-4 w-4 rounded border-slate-300" @checked(old('is_available', $editing ? $space->is_available : true))> Tersedia untuk disewa
        </label>

        <label class="{{ $label }} md:col-span-2">Deskripsi
            <textarea name="description" rows="4" required class="{{ $input }}">{{ old('description', $space->description) }}</textarea>
        </label>
        <label class="{{ $label }} md:col-span-2">Fasilitas <span class="font-normal normal-case text-slate-400">(satu per baris)</span>
            <textarea name="facilities" rows="5" class="{{ $input }}">{{ old('facilities', implode("\n", $space->facilities ?? [])) }}</textarea>
        </label>

        <div class="md:col-span-2">
            <p class="{{ $label }}">Gambar</p>
            @if ($editing)
                <img src="{{ $space->image_url }}" alt="" class="mt-2 h-28 w-44 rounded-lg bg-slate-200 object-cover" onerror="this.style.visibility='hidden'">
            @endif
            <div class="mt-2 grid gap-4 md:grid-cols-2">
                <label class="{{ $label }}">Unggah file <span class="font-normal normal-case text-slate-400">(jpg/png/webp, maks 4MB)</span>
                    <input type="file" name="image_file" accept="image/*" class="{{ $input }}">
                </label>
                <label class="{{ $label }}">atau URL gambar
                    <input type="url" name="image_url" value="{{ old('image_url', $currentImageUrl) }}" placeholder="https://..." class="{{ $input }}">
                </label>
            </div>
            @if ($editing)<p class="mt-1 text-xs text-slate-400">Kosongkan keduanya untuk mempertahankan gambar saat ini.</p>@endif
        </div>
    </div>

    <div class="mt-6 flex gap-3">
        <button class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-brand-700">{{ $editing ? 'Simpan Perubahan' : 'Tambah Ruangan' }}</button>
        <a href="{{ route('admin.spaces.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50">Batal</a>
    </div>
</form>
@endsection
