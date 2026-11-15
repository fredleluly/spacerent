@extends('seller.layout')
@php $editing = $space->exists; @endphp
@section('title', $editing ? 'Edit ruangan' : 'Tambah ruangan')
@section('heading', $editing ? 'Edit ruangan' : 'Tambah ruangan')

@section('content')
@include('partials.space-form', [
    'action' => $editing ? route('seller.spaces.update', $space) : route('seller.spaces.store'),
    'cancelUrl' => route('seller.spaces.index'),
])

@if ($editing)
<section class="card mt-6 max-w-4xl space-y-4 p-6">
    <h2 class="font-semibold">Tutup tanggal</h2>
    <p class="text-sm text-zinc-600">Tanggal yang ditutup tidak bisa dipesan, misalnya saat perawatan atau acara pribadi. Tidak bisa menutup tanggal yang sudah punya booking aktif.</p>
    @foreach ($space->blackouts->where('ends_on', '>=', today())->sortBy('starts_on') as $b)
        <div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 text-sm">
            <span>{{ $b->starts_on->translatedFormat('d M Y') }} - {{ $b->ends_on->translatedFormat('d M Y') }}@if ($b->reason), {{ $b->reason }}@endif</span>
            <form method="POST" action="{{ route('seller.blackouts.destroy', $b) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Buka kembali</button></form>
        </div>
    @endforeach
    <form method="POST" action="{{ route('seller.blackouts.store', $space) }}" class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_1.5fr_auto]" data-once>
        @csrf
        <div><label class="label" for="bo-from">Mulai</label><input id="bo-from" type="date" name="starts_on" min="{{ today()->toDateString() }}" value="{{ old('starts_on') }}" required class="input"></div>
        <div><label class="label" for="bo-to">Sampai</label><input id="bo-to" type="date" name="ends_on" min="{{ today()->toDateString() }}" value="{{ old('ends_on') }}" required class="input"></div>
        <div><label class="label" for="bo-reason">Alasan</label><input id="bo-reason" name="reason" maxlength="150" value="{{ old('reason') }}" class="input"></div>
        <button class="btn btn-dark">Tutup</button>
    </form>
</section>
@endif
@endsection
