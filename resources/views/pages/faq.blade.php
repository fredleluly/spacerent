@extends('layouts.app')

@section('title', 'FAQ - SpaceRent')

@section('content')
<div class="bg-slate-50 py-14">
    <div class="mx-auto max-w-3xl px-4">
        <p class="text-[11px] font-bold uppercase tracking-widest text-brand-600">Bantuan</p>
        <h1 class="mt-2 text-3xl font-extrabold">Pertanyaan yang Sering Diajukan</h1>
        <div class="mt-8 space-y-3">
            @foreach ([
                ['Bagaimana cara memesan ruangan?', 'Pilih ruangan di beranda, isi form booking pada halaman detail, lalu lanjutkan ke pembayaran online. Booking terkonfirmasi otomatis setelah pembayaran berhasil.'],
                ['Bagaimana cara membayar dan apa saja metodenya?', 'Pembayaran dilakukan penuh melalui Midtrans: kartu kredit/debit, virtual account bank, e-wallet (GoPay, ShopeePay), QRIS, dan gerai retail.'],
                ['Berapa lama batas waktu pembayaran?', 'Anda punya waktu 30 menit sejak booking dibuat. Jika lewat, booking kedaluwarsa otomatis dan Anda perlu membuat booking baru.'],
                ['Bagaimana kebijakan refund?', 'Pembatalan 48 jam atau lebih sebelum sewa mendapat refund 100%, 24-48 jam 50%, dan kurang dari 24 jam tidak ada refund. Lihat Syarat & Ketentuan.'],
                ['Bagaimana cara chat dengan pemilik ruangan?', 'Buka halaman booking Anda lalu gunakan tombol chat, atau gunakan tombol chat pada halaman detail ruangan untuk bertanya sebelum memesan.'],
                ['Apakah harga sudah termasuk pajak?', 'Harga yang tertera belum termasuk PPN 11%. Invoice resmi dan faktur pajak dapat diminta saat konfirmasi.'],
                ['Apakah bisa menyewa untuk jam di luar jam kerja?', 'Bisa. Layanan reservasi kami buka 24/7 dan banyak lokasi menyediakan akses di luar jam kerja.'],
                ['Apakah tersedia catering?', 'Tersedia paket snack, makan siang, dan coffee break dari mitra kami. Sebutkan kebutuhan Anda di kolom catatan.'],
                ['Bisakah menggunakan skema LKPP / E-Katalog?', 'Ya, SpaceRent siap melayani pengadaan instansi melalui LKPP / E-Katalog. Hubungi CS untuk dokumen pendukung.'],
                ['Bagaimana jika ingin membatalkan?', 'Hubungi CS atau pemilik via chat dengan menyebutkan kode booking. Ketentuan pengembalian dana mengikuti halaman Syarat & Ketentuan.'],
            ] as [$q, $a])
                <details class="group rounded-xl border border-slate-200 bg-white p-5 open:shadow-md">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-bold">
                        {{ $q }}
                        <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180"></i>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </div>
</div>
@endsection
