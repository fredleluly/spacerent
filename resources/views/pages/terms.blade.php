@extends('layouts.app')

@section('title', 'Syarat dan kebijakan batal - SpaceRent')

@section('content')
<div class="container-page max-w-3xl py-12">
    <h1 class="text-3xl font-semibold tracking-tight">Syarat dan kebijakan batal</h1>
    <p class="mt-2 text-zinc-600">Berlaku untuk semua pemesanan di SpaceRent.</p>

    <div class="mt-8 divide-y divide-zinc-200 border-y border-zinc-200">
        @foreach ([
            ['Reservasi', 'Pemesanan sah setelah pembayaran diterima. Pada ruangan Instant Book, jadwal langsung ditahan. Pada ruangan yang perlu persetujuan, pemilik menjawab dalam '.config('spacerent.approval_hours', 24).' jam dan Anda baru membayar setelah disetujui.'],
            ['Pembayaran', 'Pembayaran penuh di muka secara online lewat Midtrans (kartu, virtual account, e-wallet, QRIS). Anda membayar harga sewa, biaya kebersihan bila ada, dan biaya layanan SpaceRent. Slot ditahan '.config('midtrans.expiry_minutes', 30).' menit; setelah itu booking kedaluwarsa dan jadwal dibuka kembali.'],
            ['Jadwal ulang', 'Booking yang sudah lunas bisa dipindahkan '.config('spacerent.max_reschedules', 1).' kali ke jadwal yang masih kosong, minimal '.config('spacerent.reschedule_min_hours', 24).' jam sebelum jadwal mulai. Durasi dan harga tidak berubah.'],
            ['Penggunaan ruangan', 'Penyewa menjaga kebersihan, tidak melebihi kapasitas, dan mengikuti aturan ruangan yang tertera di halaman ruangan. Kerusakan fasilitas menjadi tanggung jawab penyewa.'],
            ['Ulasan', 'Hanya penyewa dengan booking berstatus selesai yang bisa menulis ulasan, dalam '.config('spacerent.review_window_days', 30).' hari. Ulasan boleh disertai foto dan boleh ditampilkan anonim. Identitas penulis tetap tersimpan untuk moderasi. Ulasan yang melanggar aturan dapat disembunyikan admin.'],
            ['Privasi data', 'Data pribadi hanya dipakai untuk memproses reservasi dan tidak dibagikan ke pihak ketiga tanpa persetujuan.'],
        ] as $i => [$t, $d])
            <section class="py-6">
                <h2 class="font-semibold">{{ $i + 1 }}. {{ $t }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-zinc-700">{{ $d }}</p>
            </section>
        @endforeach
    </div>

    <section class="mt-10">
        <h2 class="text-xl font-semibold">Kebijakan pembatalan</h2>
        <p class="mt-2 text-sm text-zinc-600">Pemilik memilih salah satu kebijakan untuk tiap ruangan. Kebijakan tampil di halaman ruangan sebelum Anda memesan. Biaya layanan hanya dikembalikan pada refund 100%.</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            @foreach (\App\Support\RefundPolicy::POLICIES as $p)
                <div class="rounded-2xl border border-zinc-200 p-5">
                    <h3 class="font-semibold">{{ $p['label'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-700">{{ $p['summary'] }}</p>
                </div>
            @endforeach
        </div>
        <p class="mt-5 text-sm text-zinc-700">Pembatalan setelah jadwal mulai tidak mendapat refund. Jika pemilik atau SpaceRent yang membatalkan, Anda menerima refund penuh. Refund diproses ke metode pembayaran awal.</p>
    </section>
</div>
@endsection
