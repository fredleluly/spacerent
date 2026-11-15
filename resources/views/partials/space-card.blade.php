@php
    $isFav = in_array($space->id, $favoriteIds ?? [], true);
    $canFav = auth()->check() && auth()->user()->isBuyer();
@endphp
<article class="group relative">
    <a href="{{ route('spaces.show', $space) }}" class="block rounded-2xl focus-visible:outline-offset-4">
        <div class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-zinc-100">
            <img src="{{ $space->image_url }}" alt="{{ $space->name }}" loading="lazy" class="h-full w-full object-cover transition duration-500 ease-out group-hover:scale-[1.04]">
            @if ($space->instant_book)
                <span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-zinc-900 shadow-sm"><i data-lucide="zap" class="h-3 w-3 text-brand-600"></i> Instant Book</span>
            @endif
        </div>
        <div class="mt-3 flex items-start justify-between gap-3">
            <h3 class="line-clamp-1 font-semibold text-zinc-900">{{ $space->name }}</h3>
            <span class="flex shrink-0 items-center gap-1 text-sm text-zinc-900">
                @if ($space->hasRating())
                    <i data-lucide="star" class="h-3.5 w-3.5 fill-amber-400 text-amber-400"></i>{{ number_format($space->rating, 1) }}<span class="text-zinc-600">({{ $space->rating_count }})</span>
                @else
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700">Baru</span>
                @endif
            </span>
        </div>
        <p class="mt-0.5 line-clamp-1 text-sm text-zinc-600">{{ $space->category_label }} di {{ $space->district }}, {{ $space->area }}</p>
        <p class="mt-1.5 text-sm"><span class="font-semibold text-zinc-900">{{ $space->formatted_price }}</span> <span class="text-zinc-600">per {{ $space->unit_label }}, maks. {{ $space->capacity }} orang</span></p>
    </a>

    @if ($canFav)
        <form method="POST" action="{{ route('favorites.toggle', $space) }}" class="absolute right-3 top-3">
            @csrf
            <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 shadow-sm transition hover:scale-110" aria-label="{{ $isFav ? 'Hapus dari favorit' : 'Simpan ke favorit' }}" aria-pressed="{{ $isFav ? 'true' : 'false' }}">
                <i data-lucide="heart" class="h-4 w-4 {{ $isFav ? 'fill-brand-600 text-brand-600' : 'text-zinc-700' }}"></i>
            </button>
        </form>
    @elseguest
        <a href="{{ route('login', ['redirect' => url()->full()]) }}" class="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 shadow-sm transition hover:scale-110" aria-label="Masuk untuk menyimpan favorit">
            <i data-lucide="heart" class="h-4 w-4 text-zinc-700"></i>
        </a>
    @endif
</article>
