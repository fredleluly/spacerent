@extends('layouts.app')

@section('title', 'Booking '.$booking->code.' - SpaceRent')

@section('content')
@php
    $space = $booking->space;
    $msg = "Halo SpaceRent, saya ingin konfirmasi booking {$booking->code}:\n"
        ."Ruangan: {$space->name}\n"
        .'Tanggal: '.$booking->date->translatedFormat('d F Y').($booking->start_time ? ' '.substr($booking->start_time, 0, 5) : '')."\n"
        ."Durasi: {$booking->duration} {$space->price_unit}\n"
        .'Total: Rp '.number_format($booking->total, 0, ',', '.')."\n"
        ."Atas nama: {$booking->name}";
    $waLink = 'https://wa.me/'.config('spacerent.whatsapp').'?text='.rawurlencode($msg);
@endphp
<div class="bg-slate-50 py-14">
    <div class="mx-auto max-w-2xl px-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-lg">
            <div class="text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><i data-lucide="check" class="h-7 w-7"></i></span>
                <h1 class="mt-4 text-2xl font-extrabold">Booking Berhasil Dibuat</h1>
                <p class="mt-1 text-sm text-slate-500">Simpan kode booking Anda, lalu konfirmasi ke CS via WhatsApp.</p>
                <p class="mt-5 inline-block rounded-xl bg-brand-50 px-6 py-3 text-2xl font-extrabold tracking-widest text-brand-600">{{ $booking->code }}</p>
            </div>

            <dl class="mt-8 divide-y divide-slate-100 text-sm">
                @foreach ([
                    ['Ruangan', $space->name],
                    ['Lokasi', $space->location],
                    ['Tanggal', $booking->date->translatedFormat('l, d F Y')],
                    ['Jam Mulai', $booking->start_time ? substr($booking->start_time, 0, 5).' WIB' : '-'],
                    ['Durasi', $booking->duration.' '.$space->price_unit],
                    ['Jumlah Peserta', $booking->guests.' orang'],
                    ['Nama', $booking->name],
                    ['Status', 'Menunggu Konfirmasi'],
                ] as [$k, $v])
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">{{ $k }}</dt><dd class="text-right font-semibold">{{ $v }}</dd></div>
                @endforeach
                <div class="flex justify-between gap-4 py-3"><dt class="font-bold">Total</dt><dd class="text-lg font-extrabold text-brand-600">Rp {{ number_format($booking->total, 0, ',', '.') }}</dd></div>
            </dl>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700">
                    <i data-lucide="message-square" class="h-4 w-4"></i> Konfirmasi via WhatsApp
                </a>
                <a href="{{ route('home') }}" class="inline-flex flex-1 items-center justify-center rounded-lg border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">Kembali ke Beranda</a>
            </div>
        </div>
    </div>
</div>
@endsection
