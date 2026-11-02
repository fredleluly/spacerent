@php
    $badge = [
        'pending' => 'bg-amber-100 text-amber-700',
        'confirmed' => 'bg-sky-100 text-sky-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-slate-200 text-slate-600',
    ];
    $payBadge = ['unpaid' => 'bg-amber-100 text-amber-700', 'paid' => 'bg-emerald-100 text-emerald-700', 'failed' => 'bg-red-100 text-red-700', 'expired' => 'bg-slate-200 text-slate-600', 'refunded' => 'bg-violet-100 text-violet-700'];
    $payLabels = \App\Http\Controllers\Seller\BookingController::PAYMENT_STATUSES;
    $statusLabels = \App\Http\Controllers\Seller\BookingController::STATUSES;
@endphp
<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-5 py-3">Kode</th>
                <th class="px-5 py-3">Pemesan</th>
                <th class="px-5 py-3">Ruangan</th>
                <th class="px-5 py-3">Tanggal</th>
                <th class="px-5 py-3">Total</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Pembayaran</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($bookings as $b)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-bold"><a href="{{ route('seller.bookings.show', $b) }}" class="text-brand-600 hover:underline">{{ $b->code }}</a></td>
                    <td class="px-5 py-3">{{ $b->name }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $b->space->name }}</td>
                    <td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ $b->date->format('d M Y') }}</td>
                    <td class="whitespace-nowrap px-5 py-3 font-semibold">Rp {{ number_format($b->total, 0, ',', '.') }}</td>
                    <td class="px-5 py-3"><span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge[$b->status] ?? '' }}">{{ $statusLabels[$b->status] ?? $b->status }}</span></td>
                    <td class="px-5 py-3"><span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $payBadge[$b->payment_status] ?? '' }}">{{ $payLabels[$b->payment_status] ?? $b->payment_status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">Belum ada booking.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
