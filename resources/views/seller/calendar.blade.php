@extends('seller.layout')
@section('title', 'Kalender')
@section('heading', 'Kalender booking')

@section('content')
@php
    $prev = $month->copy()->subMonth()->format('Y-m');
    $next = $month->copy()->addMonth()->format('Y-m');
    $q = fn ($m) => array_filter(['month' => $m, 'space' => $selected?->id]);
    $tone = [
        'confirmed' => 'bg-emerald-100 text-emerald-900',
        'pending' => 'bg-amber-100 text-amber-900',
        'awaiting_approval' => 'bg-amber-100 text-amber-900',
        'completed' => 'bg-zinc-200 text-zinc-700',
        'blocked' => 'bg-zinc-800 text-white',
    ];
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-1">
        <a href="{{ route('seller.calendar', $q($prev)) }}" class="btn btn-outline !p-2.5" aria-label="Bulan sebelumnya"><i data-lucide="chevron-left" class="h-4 w-4"></i></a>
        <h2 class="min-w-40 text-center text-lg font-semibold">{{ $month->translatedFormat('F Y') }}</h2>
        <a href="{{ route('seller.calendar', $q($next)) }}" class="btn btn-outline !p-2.5" aria-label="Bulan berikutnya"><i data-lucide="chevron-right" class="h-4 w-4"></i></a>
        <a href="{{ route('seller.calendar', array_filter(['space' => $selected?->id])) }}" class="btn btn-ghost">Hari ini</a>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <select name="space" class="input !w-auto" aria-label="Ruangan" onchange="this.form.submit()">
            <option value="">Semua ruangan</option>
            @foreach ($spaces as $s)<option value="{{ $s->id }}" @selected($selected?->id === $s->id)>{{ $s->name }}</option>@endforeach
        </select>
    </form>
</div>

<div class="card mt-5 overflow-hidden">
    <div class="grid grid-cols-7 border-b border-zinc-200 bg-zinc-50 text-center text-xs font-medium text-zinc-600">
        @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $d)<div class="py-2.5">{{ $d }}</div>@endforeach
    </div>
    <div class="grid grid-cols-7">
        @for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay())
            @php $items = $byDay[$d->toDateString()] ?? []; $inMonth = $d->month === $month->month; @endphp
            <div class="min-h-24 border-b border-r border-zinc-100 p-1.5 {{ $inMonth ? '' : 'bg-zinc-50/70' }}">
                <p class="mb-1 text-xs {{ $d->isToday() ? 'inline-flex h-5 w-5 items-center justify-center rounded-full bg-brand-600 font-semibold text-white' : ($inMonth ? 'font-medium text-zinc-800' : 'text-zinc-400') }}">{{ $d->day }}</p>
                <div class="space-y-1">
                    @foreach (array_slice($items, 0, 3) as $it)
                        @if ($it['url'])
                            <a href="{{ $it['url'] }}" title="{{ $it['space'] }}" class="block truncate rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ $tone[$it['type']] ?? 'bg-zinc-100' }}">{{ $it['label'] }}</a>
                        @else
                            <span title="{{ $it['space'] }}" class="block truncate rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ $tone['blocked'] }}">{{ $it['label'] }}</span>
                        @endif
                    @endforeach
                    @if (count($items) > 3)<p class="px-1 text-[11px] text-zinc-600">+{{ count($items) - 3 }} lagi</p>@endif
                </div>
            </div>
        @endfor
    </div>
</div>
<div class="mt-4 flex flex-wrap gap-4 text-xs text-zinc-700">
    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-emerald-100"></span> Terkonfirmasi</span>
    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-amber-100"></span> Menunggu bayar atau persetujuan</span>
    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-zinc-200"></span> Selesai</span>
    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-zinc-800"></span> Ditutup</span>
</div>

<section class="card mt-6 p-6">
    <h2 class="font-semibold">Penutupan tanggal</h2>
    @forelse ($blackouts as $b)
        <div class="mt-3 flex items-center justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 text-sm">
            <span><strong>{{ $b->space->name }}</strong>: {{ $b->starts_on->translatedFormat('d M Y') }} - {{ $b->ends_on->translatedFormat('d M Y') }}@if ($b->reason), {{ $b->reason }}@endif</span>
            <form method="POST" action="{{ route('seller.blackouts.destroy', $b) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Buka kembali</button></form>
        </div>
    @empty
        <p class="mt-2 text-sm text-zinc-600">Belum ada tanggal yang ditutup. Buka halaman edit ruangan untuk menutup tanggal.</p>
    @endforelse
</section>
@endsection
