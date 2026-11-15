@extends('seller.layout')
@section('title', 'Ulasan')
@section('heading', 'Ulasan ruangan')

@section('content')
<div class="mb-5 flex gap-2">
    <a href="{{ route('seller.reviews.index') }}" class="chip" @if (! request('unreplied')) aria-current="true" @endif>Semua</a>
    <a href="{{ route('seller.reviews.index', ['unreplied' => 1]) }}" class="chip" @if (request('unreplied')) aria-current="true" @endif>Belum dibalas</a>
</div>

<div class="space-y-4">
    @forelse ($reviews as $r)
        <article class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-3"><x-stars :value="$r->rating" /><span class="text-sm font-semibold">{{ $r->user?->name }}@if ($r->is_anonymous) <span class="badge badge-neutral ml-1">Anonim di publik</span>@endif</span></div>
                <span class="text-xs text-zinc-600">{{ $r->space->name }}, {{ $r->created_at->translatedFormat('d M Y') }}</span>
            </div>
            @if ($r->comment)<p class="mt-3 whitespace-pre-line text-sm">{{ $r->comment }}</p>@endif
            @if ($r->photos->isNotEmpty())<div class="mt-3 flex flex-wrap gap-2">@foreach ($r->photos as $p)<img src="{{ $p->url }}" alt="" class="h-20 w-20 rounded-xl object-cover">@endforeach</div>@endif
            @if ($r->reply)
                <div class="mt-4 rounded-xl bg-zinc-50 p-4 text-sm"><p class="font-semibold">Balasan Anda</p><p class="mt-1 whitespace-pre-line text-zinc-700">{{ $r->reply }}</p></div>
            @else
                <form method="POST" action="{{ route('seller.reviews.reply', $r) }}" class="mt-4 space-y-2" data-once>@csrf
                    <label class="label" for="reply-{{ $r->id }}">Balas ulasan (satu kali, tampil di halaman ruangan)</label>
                    <textarea id="reply-{{ $r->id }}" name="reply" rows="2" maxlength="600" required class="input"></textarea>
                    <button class="btn btn-dark btn-sm">Kirim balasan</button>
                </form>
            @endif
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-zinc-300 p-12 text-center text-sm text-zinc-600">Belum ada ulasan.</div>
    @endforelse
</div>
<div class="mt-6">{{ $reviews->links() }}</div>
@endsection
