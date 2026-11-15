@extends('layouts.app')

@section('title', $space->name.' - SpaceRent')
@section('description', \Illuminate\Support\Str::limit($space->description, 150))

@section('content')
@php
    $gallery = array_slice($space->gallery_urls, 0, 5);
    $user = auth()->user();
    $dayNames = \App\Models\Space::DAYS;
    $openDays = collect($dayNames)->filter(fn ($n, $d) => in_array($d, $space->openDays(), true))->values();
    $openText = $openDays->count() === 7 ? 'Setiap hari' : $openDays->implode(', ');
    $hoursText = fn (int $h) => $h < 48 ? $h.' jam' : intdiv($h, 24).' hari';
    $owner = $space->owner?->name ?? 'SpaceRent';
@endphp
<div class="container-page py-6">
    <div class="flex items-start justify-between gap-4">
        <h1 class="text-2xl font-semibold tracking-tight md:text-3xl">{{ $space->name }}</h1>
        <div class="flex shrink-0 items-center gap-1">
            <button type="button" class="btn btn-ghost btn-sm" data-copy="{{ url()->current() }}"><i data-lucide="share-2" class="h-4 w-4"></i> Bagikan</button>
            @if ($user?->isBuyer())
                <form method="POST" action="{{ route('favorites.toggle', $space) }}">
                    @csrf
                    <button class="btn btn-ghost btn-sm" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"><i data-lucide="heart" class="h-4 w-4 {{ $isFavorite ? 'fill-brand-600 text-brand-600' : '' }}"></i> {{ $isFavorite ? 'Tersimpan' : 'Simpan' }}</button>
                </form>
            @endif
        </div>
    </div>
    <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-zinc-700">
        @if ($space->hasRating())
            <i data-lucide="star" class="h-4 w-4 fill-amber-400 text-amber-400"></i><span class="font-semibold">{{ number_format($space->rating, 1) }}</span>
            <a href="#ulasan" class="underline underline-offset-2">{{ $space->rating_count }} ulasan</a>
        @else
            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium">Baru</span>
        @endif
        <span class="text-zinc-600">{{ $space->location }}</span>
    </p>

    {{-- Galeri --}}
    <div class="mt-5">
        @if (count($gallery) === 1)
            <div class="aspect-[16/9] overflow-hidden rounded-2xl bg-zinc-100 md:aspect-[21/9]">
                <img src="{{ $gallery[0] }}" alt="{{ $space->name }}" data-lightbox="{{ $gallery[0] }}" class="h-full w-full cursor-zoom-in object-cover" fetchpriority="high">
            </div>
        @else
            <div class="grid grid-cols-4 grid-rows-2 gap-2 overflow-hidden rounded-2xl md:h-[420px]">
                @foreach ($gallery as $i => $url)
                    <div class="{{ $i === 0 ? 'col-span-4 row-span-2 aspect-[4/3] md:col-span-2 md:aspect-auto' : 'hidden md:block' }} overflow-hidden bg-zinc-100">
                        <img src="{{ $url }}" alt="{{ $space->name }}, foto {{ $i + 1 }}" data-lightbox="{{ $url }}" class="h-full w-full cursor-zoom-in object-cover transition duration-300 hover:opacity-90" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-8 grid gap-10 lg:grid-cols-[1fr_400px] lg:gap-14">
        <div class="min-w-0">
            <div class="flex items-center justify-between gap-4 border-b border-zinc-200 pb-6">
                <div>
                    <h2 class="text-xl font-semibold">{{ $space->category_label }} di {{ $space->district }}</h2>
                    <p class="mt-1 text-sm text-zinc-600">Maks. {{ $space->capacity }} orang. Dikelola oleh {{ $owner }}.</p>
                </div>
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-lg font-semibold text-white">{{ mb_strtoupper(mb_substr($owner, 0, 1)) }}</span>
            </div>

            <ul class="space-y-5 border-b border-zinc-200 py-6">
                <li class="flex gap-4"><i data-lucide="{{ $space->instant_book ? 'zap' : 'badge-check' }}" class="mt-0.5 h-5 w-5 shrink-0"></i><div><p class="font-semibold">{{ $space->instant_book ? 'Instant Book' : 'Perlu persetujuan pemilik' }}</p><p class="text-sm text-zinc-600">{{ $space->instant_book ? 'Jadwal langsung ditahan setelah Anda memesan. Tinggal bayar.' : 'Pemilik menjawab dalam '.config('spacerent.approval_hours', 24).' jam. Anda baru membayar setelah disetujui.' }}</p></div></li>
                <li class="flex gap-4"><i data-lucide="clock" class="mt-0.5 h-5 w-5 shrink-0"></i><div><p class="font-semibold">Buka {{ $space->open_time }} - {{ $space->close_time }}</p><p class="text-sm text-zinc-600">{{ $openText }}. Durasi {{ max(1, $space->min_duration) }} sampai {{ $space->effectiveMaxDuration() }} {{ $space->unit_label }}.@if ($space->lead_time_hours > 0) Pesan minimal {{ $space->lead_time_hours }} jam sebelumnya.@endif</p></div></li>
                <li class="flex gap-4"><i data-lucide="undo-2" class="mt-0.5 h-5 w-5 shrink-0"></i><div><p class="font-semibold">Pembatalan {{ \App\Support\RefundPolicy::label($space->cancellation_policy) }}</p><p class="text-sm text-zinc-600">{{ $policy['summary'] }}</p></div></li>
            </ul>

            <section class="border-b border-zinc-200 py-6">
                <h2 class="text-xl font-semibold">Tentang ruangan</h2>
                <p class="mt-3 max-w-prose whitespace-pre-line leading-relaxed text-zinc-700">{{ $space->description }}</p>
            </section>

            @if ($space->hasCoordinates())
                <section class="border-b border-zinc-200 py-6" aria-labelledby="lokasi-title">
                    <h2 id="lokasi-title" class="text-xl font-semibold">Lokasi</h2>
                    <p class="mt-1 text-sm text-zinc-600">{{ $space->address_line ?: $space->location }}</p>
                    <div data-static-map data-lat="{{ $space->latitude }}" data-lng="{{ $space->longitude }}" class="sr-map mt-4 h-[280px] overflow-hidden rounded-2xl border border-zinc-200" role="application" aria-label="Peta lokasi {{ $space->name }}"></div>
                    <a href="https://www.openstreetmap.org/?mlat={{ $space->latitude }}&amp;mlon={{ $space->longitude }}#map=17/{{ $space->latitude }}/{{ $space->longitude }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 underline underline-offset-4"><i data-lucide="external-link" class="h-4 w-4"></i>Buka di OpenStreetMap</a>
                </section>
            @endif

            @if (! empty($space->facilities))
                <section class="border-b border-zinc-200 py-6">
                    <h2 class="text-xl font-semibold">Fasilitas</h2>
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($space->facilities as $f)
                            <li class="flex items-center gap-3 text-sm text-zinc-800"><i data-lucide="check" class="h-4 w-4 shrink-0 text-emerald-600"></i>{{ $f }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($space->rules || $space->checkin_info)
                <section class="border-b border-zinc-200 py-6">
                    <h2 class="text-xl font-semibold">Aturan dan akses</h2>
                    <div class="mt-4 grid gap-6 sm:grid-cols-2">
                        @if ($space->rules)<div><h3 class="font-semibold">Aturan ruangan</h3><p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-zinc-700">{{ $space->rules }}</p></div>@endif
                        @if ($space->checkin_info)<div><h3 class="font-semibold">Info check-in</h3><p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-zinc-700">{{ $space->checkin_info }}</p></div>@endif
                    </div>
                </section>
            @endif

            <section class="border-b border-zinc-200 py-6">
                <h2 class="text-xl font-semibold">Kebijakan pembatalan: {{ \App\Support\RefundPolicy::label($space->cancellation_policy) }}</h2>
                <ul class="mt-4 space-y-2 text-sm text-zinc-700">
                    @foreach ($policy['tiers'] as [$h, $p])
                        <li class="flex items-start gap-3"><i data-lucide="check" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"></i>Refund {{ $p }}% jika dibatalkan minimal {{ $hoursText($h) }} sebelum jadwal mulai.</li>
                    @endforeach
                    <li class="flex items-start gap-3"><i data-lucide="x" class="mt-0.5 h-4 w-4 shrink-0 text-rose-600"></i>Setelah itu tidak ada refund. Biaya layanan hanya dikembalikan pada refund 100%.</li>
                    <li class="flex items-start gap-3"><i data-lucide="shield-check" class="mt-0.5 h-4 w-4 shrink-0 text-sky-600"></i>Jika pemilik yang membatalkan, Anda menerima refund penuh.</li>
                </ul>
            </section>

            {{-- Ulasan --}}
            <section id="ulasan" class="scroll-mt-24 py-8">
                @if (! $space->hasRating())
                    <h2 class="text-xl font-semibold">Ulasan</h2>
                    <p class="mt-2 text-sm text-zinc-600">Belum ada ulasan. Ruangan ini baru, dan ulasan pertama akan muncul setelah penyewa menyelesaikan sewanya.</p>
                @else
                    <div class="flex items-center gap-3">
                        <i data-lucide="star" class="h-6 w-6 fill-amber-400 text-amber-400"></i>
                        <h2 class="text-xl font-semibold">{{ number_format($space->rating, 1) }} dari {{ $space->rating_count }} ulasan</h2>
                    </div>
                    <div class="mt-6 grid gap-8 md:grid-cols-2">
                        <ul class="space-y-2">
                            @foreach ($breakdown as $star => $row)
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="w-4 text-right tabular-nums">{{ $star }}</span>
                                    <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-100"><span class="block h-full rounded-full bg-zinc-900" style="width: {{ $row['percent'] }}%"></span></span>
                                    <span class="w-8 text-right tabular-nums text-zinc-600">{{ $row['count'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($subAverages)
                            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                @foreach ($subAverages as $key => $avg)
                                    <div><dt class="text-zinc-600">{{ \App\Models\Review::SUB_RATINGS[$key] }}</dt><dd class="font-semibold tabular-nums">{{ number_format($avg, 1) }}</dd></div>
                                @endforeach
                            </dl>
                        @endif
                    </div>

                    <div class="mt-8 flex flex-wrap gap-2">
                        @foreach (['new' => 'Terbaru', 'high' => 'Rating tertinggi', 'low' => 'Rating terendah'] as $k => $label)
                            <a href="{{ request()->fullUrlWithQuery(['rs' => $k, 'rp' => null]) }}#ulasan" class="chip !py-1.5 !text-xs" @if (request('rs', 'new') === $k) aria-current="true" @endif>{{ $label }}</a>
                        @endforeach
                        @if ($photoReviews)
                            <a href="{{ request()->boolean('photos') ? request()->fullUrlWithQuery(['photos' => null, 'rp' => null]) : request()->fullUrlWithQuery(['photos' => 1, 'rp' => null]) }}#ulasan" class="chip !py-1.5 !text-xs" @if (request()->boolean('photos')) aria-current="true" @endif><i data-lucide="camera" class="h-3.5 w-3.5"></i> Dengan foto ({{ $photoReviews }})</a>
                        @endif
                    </div>

                    <div class="mt-4 divide-y divide-zinc-200">
                        @forelse ($reviews as $r)
                            <article class="py-6">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-100 font-semibold text-zinc-700">
                                        @if ($r->is_anonymous)<i data-lucide="user" class="h-5 w-5"></i>@else{{ mb_strtoupper(mb_substr($r->author_name, 0, 1)) }}@endif
                                    </span>
                                    <div>
                                        <p class="font-semibold">{{ $r->author_name }}</p>
                                        <p class="text-xs text-zinc-600">{{ $r->created_at->translatedFormat('F Y') }}</p>
                                    </div>
                                </div>
                                <div class="mt-3"><x-stars :value="$r->rating" size="h-3.5 w-3.5" /></div>
                                @if ($r->comment)<p class="mt-2 max-w-prose whitespace-pre-line text-sm leading-relaxed text-zinc-800">{{ $r->comment }}</p>@endif
                                @if ($r->photos->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($r->photos as $p)
                                            <img src="{{ $p->url }}" alt="Foto dari ulasan {{ $r->author_name }}" data-lightbox="{{ $p->url }}" loading="lazy" class="h-24 w-24 cursor-zoom-in rounded-xl object-cover">
                                        @endforeach
                                    </div>
                                @endif
                                @if ($r->reply)
                                    <div class="mt-4 rounded-xl bg-zinc-50 p-4 text-sm">
                                        <p class="font-semibold">Balasan dari {{ $owner }}</p>
                                        <p class="mt-1 whitespace-pre-line text-zinc-700">{{ $r->reply }}</p>
                                    </div>
                                @endif
                                @auth
                                    <form method="POST" action="{{ route('reviews.report', $r) }}" class="mt-2">@csrf
                                        <button class="text-xs text-zinc-600 underline underline-offset-2 hover:text-zinc-900">Laporkan ulasan</button>
                                    </form>
                                @endauth
                            </article>
                        @empty
                            <p class="py-8 text-sm text-zinc-600">Tidak ada ulasan untuk filter ini.</p>
                        @endforelse
                    </div>
                    <div class="mt-4">{{ $reviews->fragment('ulasan')->links() }}</div>
                @endif
            </section>
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            @include('partials.booking-widget')
            @if ($user?->isBuyer())
                <form method="POST" action="{{ route('chat.start.space', $space) }}" class="mt-4 rounded-2xl border border-zinc-200 p-5">
                    @csrf
                    <p class="flex items-center gap-2 font-semibold"><i data-lucide="message-circle" class="h-4 w-4"></i> Ada pertanyaan?</p>
                    <p class="mt-1 text-xs text-zinc-600">{{ config('spacerent.reply_time') }}</p>
                    <textarea name="body" maxlength="1000" rows="2" placeholder="Tulis pertanyaan Anda" class="input mt-3">{{ old('body') }}</textarea>
                    @error('body')<p class="error">{{ $message }}</p>@enderror
                    <button class="btn btn-outline mt-3 w-full">Chat pemilik</button>
                </form>
            @endif
        </aside>
    </div>

    @if ($related->isNotEmpty())
        <section class="mt-14 border-t border-zinc-200 pt-10">
            <h2 class="text-xl font-semibold">Ruangan serupa</h2>
            <div class="mt-6 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $item)
                    @include('partials.space-card', ['space' => $item])
                @endforeach
            </div>
        </section>
    @endif
</div>

{{-- Bar pemesanan mobile --}}
<div class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur lg:hidden">
    <div class="flex items-center justify-between gap-4">
        <p class="text-sm"><span class="font-semibold">{{ $space->formatted_price }}</span> <span class="text-zinc-600">per {{ $space->unit_label }}</span></p>
        <a href="#pesan" class="btn btn-primary">Pilih jadwal</a>
    </div>
</div>
<div class="h-16 lg:hidden"></div>

<dialog id="lightbox" class="m-auto max-h-[92dvh] max-w-5xl rounded-2xl bg-transparent p-0 backdrop:bg-zinc-900/80">
    <div class="relative">
        <img src="" alt="" class="max-h-[88dvh] w-full rounded-2xl object-contain">
        <button type="button" data-dialog-close class="absolute right-3 top-3 flex h-10 w-10 items-center justify-center rounded-full bg-white shadow" aria-label="Tutup"><i data-lucide="x" class="h-5 w-5"></i></button>
        <button type="button" data-prev class="absolute left-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white shadow" aria-label="Sebelumnya"><i data-lucide="chevron-left" class="h-5 w-5"></i></button>
        <button type="button" data-next class="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white shadow" aria-label="Berikutnya"><i data-lucide="chevron-right" class="h-5 w-5"></i></button>
    </div>
</dialog>
@endsection
