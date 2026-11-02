@extends('layouts.app')

@section('title', 'Daftar - SpaceRent')

@section('content')
@php
    $role = old('role', $role ?? 'buyer');
    $input = 'mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm normal-case text-slate-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-slate-500';
@endphp
<div class="bg-slate-50 py-14">
    <div class="mx-auto max-w-md px-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-lg">
            <h1 class="text-2xl font-extrabold">Buat Akun SpaceRent</h1>
            <p class="mt-1 text-sm text-slate-500">Gratis dan hanya butuh satu menit.</p>

            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                @csrf
                @if ($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                        <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <fieldset>
                    <legend class="{{ $label }}">Daftar sebagai</legend>
                    <div class="mt-1 grid grid-cols-2 gap-3">
                        <label class="cursor-pointer rounded-xl border-2 border-slate-200 p-3 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                            <input type="radio" name="role" value="buyer" class="sr-only" @checked($role === 'buyer')>
                            <span class="block font-extrabold">Pembeli</span>
                            <span class="block text-xs text-slate-500">Ingin menyewa ruangan</span>
                        </label>
                        <label class="cursor-pointer rounded-xl border-2 border-slate-200 p-3 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                            <input type="radio" name="role" value="seller" class="sr-only" @checked($role === 'seller')>
                            <span class="block font-extrabold">Penjual</span>
                            <span class="block text-xs text-slate-500">Pemilik ruangan</span>
                        </label>
                    </div>
                </fieldset>

                <label class="{{ $label }}">Nama Lengkap
                    <input type="text" name="name" value="{{ old('name') }}" required class="{{ $input }}">
                </label>
                <label class="{{ $label }}">Email
                    <input type="email" name="email" value="{{ old('email') }}" required class="{{ $input }}">
                </label>
                <label class="{{ $label }}">No. Telepon / WhatsApp
                    <input type="tel" name="phone" value="{{ old('phone') }}" required placeholder="0812..." class="{{ $input }}">
                </label>
                <label class="{{ $label }}">Password (min. 8 karakter)
                    <input type="password" name="password" required minlength="8" class="{{ $input }}">
                </label>
                <label class="{{ $label }}">Konfirmasi Password
                    <input type="password" name="password_confirmation" required minlength="8" class="{{ $input }}">
                </label>
                <button class="w-full rounded-lg bg-brand-600 py-3 text-sm font-bold text-white hover:bg-brand-700">Daftar</button>
            </form>

            <p class="mt-5 text-center text-sm text-slate-500">Sudah punya akun? <a href="{{ route('login') }}" class="font-bold text-brand-600 hover:underline">Masuk</a></p>
        </div>
    </div>
</div>
@endsection
