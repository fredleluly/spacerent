@extends('layouts.app')

@section('title', 'Profil Saya - SpaceRent')

@section('content')
@include('buyer._nav')
@php
    $input = 'mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-slate-500';
@endphp
<div class="bg-slate-50 py-10">
    <div class="mx-auto max-w-xl px-4">
        <h1 class="text-2xl font-extrabold">Profil Saya</h1>
        @if (session('success'))<div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('success') }}</div>@endif
        <form method="POST" action="{{ route('buyer.profile') }}" class="mt-5 space-y-4 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf @method('PUT')
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            <label class="{{ $label }}">Email
                <input type="email" value="{{ $user->email }}" disabled class="{{ $input }} bg-slate-50 text-slate-400">
            </label>
            <label class="{{ $label }}">Nama Lengkap
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="{{ $input }}">
            </label>
            <label class="{{ $label }}">No. Telepon
                <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" required class="{{ $input }}">
            </label>
            <div class="border-t border-slate-100 pt-4">
                <p class="text-sm font-bold">Ganti Password <span class="font-normal text-slate-400">(opsional)</span></p>
                <div class="mt-3 space-y-4">
                    <label class="{{ $label }}">Password Saat Ini <input type="password" name="current_password" autocomplete="current-password" class="{{ $input }}"></label>
                    <label class="{{ $label }}">Password Baru <input type="password" name="password" autocomplete="new-password" class="{{ $input }}"></label>
                    <label class="{{ $label }}">Konfirmasi Password Baru <input type="password" name="password_confirmation" autocomplete="new-password" class="{{ $input }}"></label>
                </div>
            </div>
            <button class="w-full rounded-lg bg-brand-600 py-3 text-sm font-bold text-white hover:bg-brand-700">Simpan Perubahan</button>
        </form>
    </div>
</div>
@endsection
