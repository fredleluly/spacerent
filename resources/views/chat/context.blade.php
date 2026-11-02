{{-- Params: $conversation, $bookingRoute, $toggleRoute, $indexRoute, $showSeller --}}
@php $booking = $conversation->booking; @endphp
<div class="rounded-xl bg-white p-5 text-sm shadow-sm">
    <p class="text-xs text-slate-400">Pembeli</p>
    <p class="font-bold">{{ $conversation->user?->name ?? $conversation->name }}</p>
    <p class="text-slate-500">{{ $conversation->user?->email ?? $conversation->email }}</p>
    @if (! empty($showSeller))
        <p class="mt-3 text-xs text-slate-400">Penjual</p>
        <p class="font-semibold">{{ $conversation->space?->owner?->name ?? '-' }}</p>
    @endif
    <p class="mt-3 text-xs text-slate-400">Ruangan</p>
    <p class="font-semibold">{{ $conversation->space?->name ?? '-' }}</p>
    @if ($booking)
        <p class="mt-3 text-xs text-slate-400">Booking</p>
        <p class="font-semibold">{{ $booking->code }}</p>
        <p class="text-xs text-slate-500">Status: {{ $booking->status }} &middot; Bayar: {{ $booking->payment_status ?? '-' }}</p>
        <a href="{{ route($bookingRoute, $booking) }}" class="mt-2 inline-block text-xs font-bold text-brand-600 hover:underline">Buka booking &rarr;</a>
    @endif
    <form method="POST" action="{{ route($toggleRoute, $conversation) }}" class="mt-4">
        @csrf @method('PATCH')
        <button class="w-full rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold hover:bg-slate-50">{{ $conversation->status === 'open' ? 'Tutup percakapan' : 'Buka kembali' }}</button>
    </form>
    <a href="{{ route($indexRoute) }}" class="mt-3 block text-center text-xs text-slate-500 hover:underline">&larr; Kembali ke inbox</a>
</div>
