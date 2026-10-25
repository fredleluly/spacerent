@extends('layouts.app')

@section('title', 'Hubungi Kami - SpaceRent')

@section('content')
@php
    $input = 'mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-slate-500';
@endphp
<div class="bg-slate-50 py-14">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 lg:grid-cols-[1fr_1.3fr]">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-brand-600">Kontak</p>
            <h1 class="mt-2 text-3xl font-extrabold">Hubungi Kami</h1>
            <p class="mt-3 text-slate-600">Butuh rekomendasi ruangan atau penawaran khusus untuk perusahaan? Tim kami siap membantu.</p>
            <ul class="mt-8 space-y-4 text-sm">
                <li class="flex gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><i data-lucide="map-pin" class="h-4 w-4"></i></span><span class="pt-2">{{ config('spacerent.address') }}</span></li>
                <li class="flex gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><i data-lucide="phone" class="h-4 w-4"></i></span><span class="pt-2">{{ config('spacerent.hotline') }}</span></li>
                <li class="flex gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><i data-lucide="mail" class="h-4 w-4"></i></span><span class="pt-2">{{ config('spacerent.email') }}</span></li>
            </ul>
        </div>

        <form method="POST" action="{{ route('contact.send') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-lg">
            @csrf
            @if (session('success'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="{{ $label }}">Nama<input type="text" name="name" value="{{ old('name') }}" required class="{{ $input }}"></label>
                <label class="{{ $label }}">Email<input type="email" name="email" value="{{ old('email') }}" required class="{{ $input }}"></label>
                <label class="{{ $label }}">No. Telepon<input type="tel" name="phone" value="{{ old('phone') }}" class="{{ $input }}"></label>
                <label class="{{ $label }}">Subjek<input type="text" name="subject" value="{{ old('subject') }}" required class="{{ $input }}"></label>
            </div>
            <label class="{{ $label }} mt-4">Pesan<textarea name="message" rows="5" required class="{{ $input }}">{{ old('message') }}</textarea></label>
            <button type="submit" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-6 py-3 text-sm font-bold text-white hover:bg-brand-700">
                <i data-lucide="send" class="h-4 w-4"></i> Kirim Pesan
            </button>
        </form>
    </div>
</div>
@endsection
