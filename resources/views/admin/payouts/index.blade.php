@extends('admin.layout')
@section('title', 'Penarikan')
@section('heading', 'Penarikan dana penjual')

@section('content')
@php $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
<div class="mb-5 flex flex-wrap items-center gap-2">
    <a href="{{ route('admin.payouts.index') }}" class="chip" @if (! request('status')) aria-current="true" @endif>Semua</a>
    @foreach (\App\Models\Payout::STATUSES as $k => $label)
        <a href="{{ route('admin.payouts.index', ['status' => $k]) }}" class="chip" @if (request('status') === $k) aria-current="true" @endif>{{ $label }}</a>
    @endforeach
    <span class="ml-auto text-sm text-zinc-700">Menunggu transfer: <strong>{{ $rp($waiting) }}</strong></span>
</div>

<div class="space-y-4">
    @forelse ($payouts as $p)
        <article class="card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xl font-semibold tabular-nums">{{ $rp($p->amount) }}</p>
                    <p class="text-sm text-zinc-700">{{ $p->user->name }} ({{ $p->user->email }}), {{ $p->bookings_count }} booking</p>
                    <p class="mt-1 text-sm"><strong>{{ $p->bank_name }}</strong> {{ $p->bank_account_number }} a.n. {{ $p->bank_account_name }}</p>
                    <p class="mt-1 text-xs text-zinc-600">Diminta {{ $p->requested_at->translatedFormat('d M Y, H:i') }}@if ($p->processed_at), diproses {{ $p->processed_at->translatedFormat('d M Y, H:i') }}@endif @if ($p->reference), ref {{ $p->reference }}@endif @if ($p->note), {{ $p->note }}@endif</p>
                </div>
                <span class="badge {{ ['requested' => 'badge-warn', 'paid' => 'badge-success', 'rejected' => 'badge-danger'][$p->status] }}">{{ $p->status_label }}</span>
            </div>
            @if ($p->status === 'requested')
                <div class="mt-4 grid gap-3 border-t border-zinc-200 pt-4 md:grid-cols-2">
                    <form method="POST" action="{{ route('admin.payouts.paid', $p) }}" class="flex gap-2" data-once data-confirm="Tandai sudah ditransfer {{ $rp($p->amount) }}?">@csrf
                        <input name="reference" maxlength="120" placeholder="No. referensi transfer" class="input">
                        <button class="btn btn-primary">Sudah ditransfer</button>
                    </form>
                    <form method="POST" action="{{ route('admin.payouts.reject', $p) }}" class="flex gap-2" data-once>@csrf
                        <input name="note" maxlength="200" required placeholder="Alasan penolakan" class="input">
                        <button class="btn btn-danger">Tolak</button>
                    </form>
                </div>
            @endif
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-zinc-300 p-12 text-center text-sm text-zinc-600">Belum ada permintaan penarikan.</div>
    @endforelse
</div>
<div class="mt-6">{{ $payouts->links() }}</div>
@endsection
