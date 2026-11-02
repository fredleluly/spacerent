@extends('seller.layout')
@section('title', 'Ruangan')
@section('heading', 'Kelola Ruangan')

@section('content')
@if (session('error'))<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>@endif
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex flex-wrap gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama ruangan..." class="w-60 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
        <select name="category" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
            <option value="">Semua Kategori</option>
            @foreach (\App\Models\Space::CATEGORIES as $slug => $c)<option value="{{ $slug }}" @selected(request('category') === $slug)>{{ $c['label'] }}</option>@endforeach
        </select>
        <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-bold text-white hover:bg-slate-900">Cari</button>
    </form>
    <a href="{{ route('seller.spaces.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700"><i data-lucide="plus" class="h-4 w-4"></i>Tambah Ruangan</a>
</div>

<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500">
            <tr><th class="px-5 py-3">Ruangan</th><th class="px-5 py-3">Kategori</th><th class="px-5 py-3">Lokasi</th><th class="px-5 py-3">Kapasitas</th><th class="px-5 py-3">Harga</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($spaces as $s)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <img src="{{ $s->image_url }}" alt="" class="h-11 w-14 rounded-md bg-slate-200 object-cover" onerror="this.style.visibility='hidden'">
                            <span class="font-semibold">{{ $s->name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-slate-500">{{ $s->category_label }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $s->location }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $s->capacity }}</td>
                    <td class="whitespace-nowrap px-5 py-3 font-semibold">{{ $s->formatted_price }}<span class="text-xs font-normal text-slate-400">/{{ $s->price_unit }}</span></td>
                    <td class="px-5 py-3"><span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $s->is_available ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $s->is_available ? 'Tersedia' : 'Penuh' }}</span></td>
                    <td class="whitespace-nowrap px-5 py-3 text-right">
                        <a href="{{ route('spaces.show', $s) }}" target="_blank" class="inline-block rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-brand-600" aria-label="Lihat"><i data-lucide="eye" class="h-4 w-4"></i></a>
                        <a href="{{ route('seller.spaces.edit', $s) }}" class="inline-block rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-brand-600" aria-label="Edit"><i data-lucide="pencil" class="h-4 w-4"></i></a>
                        <form method="POST" action="{{ route('seller.spaces.destroy', $s) }}" class="inline" data-confirm="Hapus ruangan {{ $s->name }}? Seluruh booking terkait ikut terhapus.">
                            @csrf @method('DELETE')
                            <button class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Hapus"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Belum ada ruangan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $spaces->links() }}</div>
@endsection
