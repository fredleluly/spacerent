<x-mail::message>
# Pembayaran Berhasil

Halo {{ $booking->name }}, pembayaran booking Anda telah kami terima dan booking **dikonfirmasi**.

<x-mail::table>
| | |
|:--|:--|
| Kode Booking | {{ $booking->code }} |
| Ruangan | {{ $booking->space->name }} |
| Tanggal | {{ \Illuminate\Support\Carbon::parse($booking->date)->translatedFormat('d F Y') }}{{ $booking->start_time ? ' '.substr($booking->start_time, 0, 5) : '' }} |
| Durasi | {{ $booking->duration }} {{ $booking->space->price_unit }} |
| Total | Rp {{ number_format($booking->total, 0, ',', '.') }} |
</x-mail::table>

<x-mail::button :url="route('booking.show', $booking)">
Lihat Booking
</x-mail::button>

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
