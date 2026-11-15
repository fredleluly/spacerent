@extends('layouts.app')

@section('title', 'Masuk - SpaceRent')

@section('content')
<div class="container-page grid min-h-[70dvh] items-center gap-12 py-12 lg:grid-cols-2">
    <div class="mx-auto w-full max-w-md">
        <h1 class="text-3xl font-semibold tracking-tight">Selamat datang kembali</h1>
        <p class="mt-2 text-zinc-600">Masuk untuk memesan ruangan atau mengelola pesanan Anda.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5" data-once>
            @csrf
            <input type="hidden" name="redirect" value="{{ old('redirect', $redirect) }}">
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p id="email-error" class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="label">Kata sandi</label>
                <input id="password" type="password" name="password" required autocomplete="current-password" class="input">
            </div>
            <label class="flex items-center gap-2 text-sm text-zinc-700"><input type="checkbox" name="remember" class="h-4 w-4 rounded border-zinc-300 accent-brand-600"> Ingat saya</label>
            <button type="submit" class="btn btn-primary btn-lg w-full" data-loading="Memproses...">Masuk</button>
        </form>

        <p class="mt-6 text-sm text-zinc-600">Belum punya akun? <a href="{{ route('register') }}" class="font-semibold text-brand-700 underline underline-offset-4">Daftar gratis</a></p>
    </div>
    <div class="hidden aspect-[4/5] overflow-hidden rounded-2xl bg-zinc-100 lg:block">
        <img src="https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=1200&q=80" alt="" class="h-full w-full object-cover">
    </div>
</div>
@endsection
