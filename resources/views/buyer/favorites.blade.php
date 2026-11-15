@extends('layouts.app')

@section('title', 'Favorit - SpaceRent')

@section('content')
@include('buyer._nav')
<div class="container-page py-8">
    <h1 class="text-2xl font-semibold tracking-tight">Ruangan favorit</h1>

    @if ($spaces->isEmpty())
        <div class="mx-auto mt-12 max-w-md rounded-2xl border border-dashed border-zinc-300 p-10 text-center">
            <i data-lucide="heart" class="mx-auto h-10 w-10 text-zinc-500"></i>
            <h2 class="mt-4 text-lg font-semibold">Belum ada favorit</h2>
            <p class="mt-1 text-sm text-zinc-600">Ketuk ikon hati pada ruangan untuk menyimpannya di sini.</p>
            <a href="{{ route('home') }}#ruangan" class="btn btn-primary mt-5">Jelajahi ruangan</a>
        </div>
    @else
        <div class="mt-8 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($spaces as $space)
                @include('partials.space-card', ['space' => $space, 'favoriteIds' => $spaces->pluck('id')->all()])
            @endforeach
        </div>
    @endif
</div>
@endsection
