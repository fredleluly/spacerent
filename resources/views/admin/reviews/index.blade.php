@extends('admin.layout')
@section('title', 'Ulasan')
@section('heading', 'Moderasi ulasan')

@section('content')
<div class="mb-5 flex flex-wrap gap-2">
    <a href="{{ route('admin.reviews.index') }}" class="chip" @if (! request()->hasAny(['reported', 'status'])) aria-current="true" @endif>Semua</a>
    <a href="{{ route('admin.reviews.index', ['reported' => 1]) }}" class="chip" @if (request('reported')) aria-current="true" @endif>Dilaporkan</a>
    <a href="{{ route('admin.reviews.index', ['status' => 'hidden']) }}" class="chip" @if (request('status') === 'hidden') aria-current="true" @endif>Disembunyikan</a>
</div>

<div class="space-y-4">
    @forelse ($reviews as $r)
        <article class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-3">
                    <x-stars :value="$r->rating" />
                    <span class="text-sm font-semibold">{{ $r->user?->name }} <span class="font-normal text-zinc-600">({{ $r->user?->email }})</span></span>
                    @if ($r->is_anonymous)<span class="badge badge-neutral">Anonim di publik</span>@endif
                    @if ($r->status === 'hidden')<span class="badge badge-danger">Disembunyikan</span>@endif
                    @if ($r->reported_count > 0)<span class="badge badge-warn">{{ $r->reported_count }} laporan</span>@endif
                </div>
                <span class="text-xs text-zinc-600">{{ $r->space->name }}, {{ $r->created_at->translatedFormat('d M Y') }}</span>
            </div>
            @if ($r->comment)<p class="mt-3 whitespace-pre-line text-sm">{{ $r->comment }}</p>@endif
            @if ($r->photos->isNotEmpty())<div class="mt-3 flex flex-wrap gap-2">@foreach ($r->photos as $p)<img src="{{ $p->url }}" alt="" class="h-20 w-20 rounded-xl object-cover">@endforeach</div>@endif
            <form method="POST" action="{{ route('admin.reviews.toggle', $r) }}" class="mt-4">@csrf
                <button class="btn {{ $r->status === 'published' ? 'btn-danger' : 'btn-outline' }} btn-sm">{{ $r->status === 'published' ? 'Sembunyikan ulasan' : 'Tampilkan kembali' }}</button>
            </form>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-zinc-300 p-12 text-center text-sm text-zinc-600">Tidak ada ulasan.</div>
    @endforelse
</div>
<div class="mt-6">{{ $reviews->links() }}</div>
@endsection
