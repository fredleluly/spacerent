@props(['booking'])
@php
    $s = $booking->status;
    $p = $booking->payment_status;
    [$label, $cls] = match (true) {
        $s === 'awaiting_approval' => ['Menunggu persetujuan', 'badge-warn'],
        $s === 'pending' && $booking->isPayable() => ['Perlu dibayar', 'badge-warn'],
        $s === 'pending' => ['Kedaluwarsa', 'badge-neutral'],
        $s === 'confirmed' => ['Terkonfirmasi', 'badge-success'],
        $s === 'completed' => ['Selesai', 'badge-neutral'],
        $s === 'no_show' => ['Tidak hadir', 'badge-neutral'],
        $s === 'cancelled' && $p === 'refund_pending' => ['Dibatalkan, refund diproses', 'badge-info'],
        $s === 'cancelled' && $p === 'refunded' => ['Dibatalkan, dana kembali', 'badge-info'],
        $s === 'cancelled' => ['Dibatalkan', 'badge-danger'],
        default => [ucfirst($s), 'badge-neutral'],
    };
@endphp
<span {{ $attributes->class(['badge', $cls]) }}>{{ $label }}</span>
