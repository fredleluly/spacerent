@php $showRoute = $panel.'.bookings.show'; @endphp
<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-zinc-200 bg-zinc-50 text-xs font-medium text-zinc-600">
            <tr>
                <th class="px-5 py-3">Kode</th>
                <th class="px-5 py-3">Pemesan</th>
                <th class="px-5 py-3">Ruangan</th>
                <th class="px-5 py-3">Jadwal</th>
                <th class="px-5 py-3 text-right">Total</th>
                <th class="px-5 py-3">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            @forelse ($bookings as $b)
                <tr class="hover:bg-zinc-50">
                    <td class="px-5 py-3 font-semibold"><a href="{{ route($showRoute, $b) }}" class="text-brand-700 hover:underline">{{ $b->code }}</a></td>
                    <td class="px-5 py-3">{{ $b->name }}</td>
                    <td class="px-5 py-3 text-zinc-600">{{ $b->space->name }}</td>
                    <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $b->starts_at->format('d M Y') }}@if ($b->start_time), {{ substr($b->start_time, 0, 5) }}@endif</td>
                    <td class="whitespace-nowrap px-5 py-3 text-right font-semibold">Rp {{ number_format($b->total, 0, ',', '.') }}</td>
                    <td class="px-5 py-3"><x-status-badge :booking="$b" />@if ($b->refund_status === 'pending')<span class="badge badge-info ml-1">Refund</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-12 text-center text-zinc-600">Belum ada booking.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
