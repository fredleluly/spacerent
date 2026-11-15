<x-mail::message>
# {{ $title }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
<x-mail::table>
| | |
|:--|:--|
| Kode Booking | {{ $booking->code }} |
| Ruangan | {{ $booking->space->name }} |
| Jadwal | {{ $booking->starts_at?->translatedFormat('l, d F Y H:i') }} |
| Total | Rp {{ number_format($booking->total, 0, ',', '.') }} |
</x-mail::table>

@if ($actionLabel)
<x-mail::button :url="$actionUrl ?: route('booking.show', $booking)">
{{ $actionLabel }}
</x-mail::button>
@endif

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
