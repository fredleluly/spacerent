@extends('layouts.app')

@section('title', 'Pertanyaan umum - SpaceRent')

@section('content')
<div class="container-page max-w-3xl py-12">
    <h1 class="text-3xl font-semibold tracking-tight">Pertanyaan umum</h1>
    <div class="mt-8 divide-y divide-zinc-200 border-y border-zinc-200">
        @foreach ([
            ['Bagaimana cara memesan ruangan?', 'Buka halaman ruangan, pilih tanggal di kalender, lalu pilih jam mulai dan durasi. Jam yang sudah terisi tidak bisa dipilih. Setelah memesan, bayar online dalam '.config('midtrans.expiry_minutes', 30).' menit.'],
            ['Apa bedanya Instant Book dan perlu persetujuan?', 'Instant Book langsung menahan jadwal untuk Anda. Ruangan yang perlu persetujuan mengirim permintaan ke pemilik dulu. Anda tidak dikenai biaya sampai pemilik setuju dan Anda membayar.'],
            ['Apa saja metode pembayarannya?', 'Kartu kredit atau debit, virtual account bank, e-wallet (GoPay, ShopeePay), dan QRIS, semuanya lewat Midtrans.'],
            ['Bisakah memesan jauh hari?', 'Bisa. Tiap ruangan menampilkan batas pemesanan di kalender. Pembatalan dihitung dari jadwal mulai, jadi memesan jauh hari tetap aman selama masih dalam batas refund.'],
            ['Bagaimana kebijakan refund?', 'Ada tiga kebijakan: Fleksibel, Moderat, dan Ketat. Pemilik memilihnya per ruangan dan kebijakannya tampil sebelum Anda memesan. Lihat halaman syarat untuk rinciannya.'],
            ['Bagaimana cara membatalkan atau mengubah jadwal?', 'Buka Pesanan saya, pilih booking, lalu klik Batalkan booking atau Ubah jadwal. Jumlah refund tampil sebelum Anda mengonfirmasi.'],
            ['Apa yang terjadi jika pemilik membatalkan?', 'Anda menerima refund penuh, termasuk biaya layanan.'],
            ['Bagaimana cara menulis ulasan?', 'Setelah booking berstatus selesai, buka halaman booking lalu isi rating, komentar, dan foto. Anda juga bisa memilih tampil anonim.'],
            ['Bagaimana cara chat dengan pemilik?', 'Gunakan tombol chat di halaman ruangan untuk bertanya sebelum memesan, atau di halaman booking setelah memesan.'],
        ] as [$q, $a])
            <details class="group py-5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold [&::-webkit-details-marker]:hidden">
                    {{ $q }}
                    <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-zinc-600 transition group-open:rotate-180"></i>
                </summary>
                <p class="mt-3 text-sm leading-relaxed text-zinc-700">{{ $a }}</p>
            </details>
        @endforeach
    </div>
    <p class="mt-8 text-sm text-zinc-600">Belum terjawab? <a href="{{ route('contact') }}" class="font-semibold text-brand-700 underline underline-offset-4">Hubungi kami</a>.</p>
</div>
@endsection
