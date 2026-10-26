@extends('admin.layout')
@section('title', 'Booking '.$booking->code)
@section('heading', 'Detail Booking')

@section('content')
@php $space = $booking->space; @endphp
<a href="{{ route('admin.bookings.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600"><i data-lucide="arrow-left" class="h-4 w-4"></i>Kembali</a>

<div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <p class="text-xs text-slate-400">Kode Booking</p>
        <p class="text-2xl font-extrabold tracking-widest text-brand-600">{{ $booking->code }}</p>
        <dl class="mt-5 divide-y divide-slate-100 text-sm">
            @foreach ([
                ['Ruangan', $space->name],
                ['Lokasi', $space->location],
                ['Tanggal', $booking->date->translatedFormat('l, d F Y')],
                ['Jam Mulai', $booking->start_time ? substr($booking->start_time, 0, 5) : '-'],
                ['Durasi', $booking->duration.' '.$space->price_unit],
                ['Jumlah Peserta', $booking->guests.' orang'],
                ['Total', 'Rp '.number_format($booking->total, 0, ',', '.')],
                ['Catatan', $booking->notes ?: '-'],
                ['Dibuat', $booking->created_at->format('d M Y H:i')],
            ] as [$k, $v])
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-slate-500">{{ $k }}</dt><dd class="text-right font-semibold">{{ $v }}</dd></div>
            @endforeach
        </dl>
    </section>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="font-bold">Pemesan</h2>
            <p class="mt-3 text-sm font-semibold">{{ $booking->name }}</p>
            @if ($booking->company)<p class="text-sm text-slate-500">{{ $booking->company }}</p>@endif
            <p class="mt-2 text-sm text-slate-500">{{ $booking->email }}</p>
            <p class="text-sm text-slate-500">{{ $booking->phone }}</p>
            <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/\D/', '', $booking->phone)) }}?text={{ rawurlencode("Halo {$booking->name}, terkait booking {$booking->code} di SpaceRent...") }}" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700"><i data-lucide="message-square" class="h-4 w-4"></i>Chat WhatsApp</a>
        </section>

        <form method="POST" action="{{ route('admin.bookings.update', $booking) }}" class="rounded-xl border border-slate-200 bg-white p-6">
            @csrf @method('PATCH')
            <h2 class="font-bold">Ubah Status</h2>
            <select name="status" class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none">
                @foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected($booking->status === $v)>{{ $l }}</option>@endforeach
            </select>
            <button class="mt-3 w-full rounded-lg bg-brand-600 py-2.5 text-sm font-bold text-white hover:bg-brand-700">Simpan Status</button>
        </form>

        <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" data-confirm="Hapus booking {{ $booking->code }}? Tindakan ini tidak bisa dibatalkan.">
            @csrf @method('DELETE')
            <button class="w-full rounded-lg border border-red-200 bg-white py-2.5 text-sm font-bold text-red-600 hover:bg-red-50">Hapus Booking</button>
        </form>
    </div>
</div>
@endsection
