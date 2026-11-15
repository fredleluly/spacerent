@extends('seller.layout')
@section('title', 'Pendapatan')
@section('heading', 'Pendapatan dan penarikan')

@section('content')
@php $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); $hasBank = $seller->bank_account_number; @endphp
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
        ['Saldo tersedia', $balances['available'], 'Siap ditarik'],
        ['Menunggu masa tahan', $balances['upcoming'], 'Booking berjalan atau baru selesai'],
        ['Sedang diproses', $balances['processing'], 'Menunggu transfer admin'],
        ['Sudah dicairkan', $balances['paid'], 'Total transfer berhasil'],
    ] as [$label, $value, $hint])
        <div class="card p-5"><p class="text-sm text-zinc-600">{{ $label }}</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $rp($value) }}</p><p class="mt-1 text-xs text-zinc-600">{{ $hint }}</p></div>
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_1.1fr]">
    <section class="card p-6">
        <h2 class="font-semibold">Rekening pencairan</h2>
        <form method="POST" action="{{ route('seller.payouts.bank') }}" class="mt-4 space-y-4" data-once>
            @csrf
            <div><label class="label" for="bank_name">Nama bank</label><input id="bank_name" name="bank_name" value="{{ old('bank_name', $seller->bank_name) }}" required class="input" placeholder="BCA, Mandiri, BNI"></div>
            <div><label class="label" for="bank_account_number">Nomor rekening</label><input id="bank_account_number" name="bank_account_number" value="{{ old('bank_account_number', $seller->bank_account_number) }}" required inputmode="numeric" class="input">@error('bank_account_number')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="bank_account_name">Atas nama</label><input id="bank_account_name" name="bank_account_name" value="{{ old('bank_account_name', $seller->bank_account_name) }}" required class="input"></div>
            <button class="btn btn-dark" data-loading="Menyimpan...">Simpan rekening</button>
        </form>

        <div class="mt-6 border-t border-zinc-200 pt-6">
            <h2 class="font-semibold">Tarik dana</h2>
            <p class="mt-1 text-sm text-zinc-600">Minimum {{ $rp($min) }}. Dana cair {{ config('spacerent.payout_hold_days', 1) }} hari setelah sewa selesai. Komisi platform {{ rtrim(rtrim(number_format($commission, 2), '0'), '.') }}% dari sewa dan biaya kebersihan; biaya layanan pembeli milik platform.</p>
            <form method="POST" action="{{ route('seller.payouts.request') }}" class="mt-4" data-once data-confirm="Tarik saldo tersedia {{ $rp($balances['available']) }} ke {{ $seller->bank_name }} {{ $seller->bank_account_number }}?">
                @csrf
                <button class="btn btn-primary" @disabled(! $hasBank || $balances['available'] < $min)>Tarik {{ $rp($balances['available']) }}</button>
                @unless ($hasBank)<p class="help">Isi rekening dulu.</p>@endunless
            </form>
        </div>
    </section>

    <section class="card p-6">
        <h2 class="font-semibold">Riwayat penarikan</h2>
        <ul class="mt-4 divide-y divide-zinc-100 text-sm">
            @forelse ($history as $p)
                <li class="flex items-center justify-between gap-3 py-3">
                    <div><p class="font-semibold tabular-nums">{{ $rp($p->amount) }}</p><p class="text-xs text-zinc-600">{{ $p->requested_at->translatedFormat('d M Y, H:i') }}, {{ $p->bookings_count }} booking @if ($p->reference), ref {{ $p->reference }}@endif @if ($p->note), {{ $p->note }}@endif</p></div>
                    <span class="badge {{ ['requested' => 'badge-warn', 'paid' => 'badge-success', 'rejected' => 'badge-danger'][$p->status] }}">{{ $p->status_label }}</span>
                </li>
            @empty
                <li class="py-8 text-center text-zinc-600">Belum ada penarikan.</li>
            @endforelse
        </ul>
    </section>
</div>

<section class="card mt-6 overflow-hidden">
    <h2 class="border-b border-zinc-200 px-5 py-4 font-semibold">Rincian pendapatan per booking</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs font-medium text-zinc-600"><tr><th class="px-5 py-3">Booking</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Dasar</th><th class="px-5 py-3 text-right">Komisi</th><th class="px-5 py-3 text-right">Bersih</th><th class="px-5 py-3">Cair</th></tr></thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($recent as $b)
                    @php $at = $service->availableAt($b); @endphp
                    <tr>
                        <td class="px-5 py-3"><a href="{{ route('seller.bookings.show', $b) }}" class="font-semibold text-brand-700 hover:underline">{{ $b->code }}</a><br><span class="text-xs text-zinc-600">{{ $b->space->name }}</span></td>
                        <td class="px-5 py-3"><x-status-badge :booking="$b" /></td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ $rp($service->retainedBase($b)) }}</td>
                        <td class="px-5 py-3 text-right tabular-nums text-zinc-600">-{{ $rp($service->commission($b)) }}</td>
                        <td class="px-5 py-3 text-right font-semibold tabular-nums">{{ $rp($service->earning($b)) }}</td>
                        <td class="px-5 py-3 text-zinc-600">@if ($b->payout_id) Dalam penarikan @elseif ($at) {{ $at->translatedFormat('d M Y') }} @else Setelah selesai @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-zinc-600">Belum ada pendapatan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
