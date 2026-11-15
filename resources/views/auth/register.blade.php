@extends('layouts.app')

@section('title', 'Daftar - SpaceRent')

@section('content')
@php $role = old('role', $role ?? 'buyer'); @endphp
<div class="container-page grid items-start gap-12 py-12 lg:grid-cols-2">
    <div class="mx-auto w-full max-w-md">
        <h1 class="text-3xl font-semibold tracking-tight">Buat akun SpaceRent</h1>
        <p class="mt-2 text-zinc-600">Gratis, selesai dalam satu menit.</p>

        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="alert">
                <p class="font-semibold">Periksa kembali isian Anda</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5" data-once>
            @csrf
            <fieldset>
                <legend class="label">Saya ingin</legend>
                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer rounded-xl border border-zinc-300 p-4 transition has-[:checked]:border-zinc-900 has-[:checked]:bg-zinc-50 has-[:checked]:ring-1 has-[:checked]:ring-zinc-900">
                        <input type="radio" name="role" value="buyer" class="sr-only" @checked($role === 'buyer')>
                        <span class="block font-semibold">Menyewa ruangan</span>
                        <span class="mt-0.5 block text-xs text-zinc-600">Akun pembeli</span>
                    </label>
                    <label class="cursor-pointer rounded-xl border border-zinc-300 p-4 transition has-[:checked]:border-zinc-900 has-[:checked]:bg-zinc-50 has-[:checked]:ring-1 has-[:checked]:ring-zinc-900">
                        <input type="radio" name="role" value="seller" class="sr-only" @checked($role === 'seller')>
                        <span class="block font-semibold">Menyewakan ruangan</span>
                        <span class="mt-0.5 block text-xs text-zinc-600">Akun penjual</span>
                    </label>
                </div>
            </fieldset>
            <div>
                <label for="name" class="label">Nama lengkap</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" class="input">
            </div>
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="input">
            </div>
            <div>
                <label for="phone" class="label">Nomor WhatsApp</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel" placeholder="0812 3456 7890" class="input">
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="password" class="label">Kata sandi</label>
                    <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" class="input">
                    <p class="help">Minimal 8 karakter.</p>
                </div>
                <div>
                    <label for="password_confirmation" class="label">Ulangi kata sandi</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="input">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-full" data-loading="Memproses...">Buat akun</button>
        </form>

        <p class="mt-6 text-sm text-zinc-600">Sudah punya akun? <a href="{{ route('login') }}" class="font-semibold text-brand-700 underline underline-offset-4">Masuk</a></p>
    </div>
    <div class="sticky top-24 hidden aspect-[4/5] overflow-hidden rounded-2xl bg-zinc-100 lg:block">
        <img src="https://images.unsplash.com/photo-1590650153855-d9e808231d41?auto=format&fit=crop&w=1200&q=80" alt="" class="h-full w-full object-cover">
    </div>
</div>
@endsection
