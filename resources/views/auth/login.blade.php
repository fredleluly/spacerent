@extends('layouts.app')

@section('title', 'Masuk - SpaceRent')

@section('content')
<div class="bg-slate-50 py-14">
    <div class="mx-auto max-w-md px-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-lg">
            <h1 class="text-2xl font-extrabold">Masuk ke SpaceRent</h1>
            <p class="mt-1 text-sm text-slate-500">Masuk sebagai pembeli, penjual, atau admin.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="redirect" value="{{ old('redirect', $redirect) }}">
                @error('email')<div class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">{{ $message }}</div>@enderror
                <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500">Email
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm normal-case text-slate-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                </label>
                <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500">Password
                    <input type="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" class="rounded border-slate-300"> Ingat saya</label>
                <button class="w-full rounded-lg bg-brand-600 py-3 text-sm font-bold text-white hover:bg-brand-700">Masuk</button>
            </form>

            <p class="mt-5 text-center text-sm text-slate-500">Belum punya akun? <a href="{{ route('register') }}" class="font-bold text-brand-600 hover:underline">Daftar</a></p>
        </div>
    </div>
</div>
@endsection
