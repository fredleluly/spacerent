@extends('layouts.app')

@section('title', 'Syarat & Ketentuan - SpaceRent')

@section('content')
<div class="bg-slate-50 py-14">
    <div class="mx-auto max-w-3xl px-4">
        <p class="text-[11px] font-bold uppercase tracking-widest text-brand-600">Legal</p>
        <h1 class="mt-2 text-3xl font-extrabold">Syarat &amp; Ketentuan</h1>
        <div class="mt-8 space-y-4">
            @foreach ([
                ['Reservasi', 'Reservasi dianggap sah setelah mendapat konfirmasi dari tim SpaceRent melalui WhatsApp atau email dan pembayaran telah diterima.'],
                ['Pembayaran', 'Pembayaran dilakukan penuh (100%) secara online melalui Midtrans (kartu, virtual account, e-wallet, QRIS, dan lainnya). Batas waktu pembayaran adalah 30 menit sejak booking dibuat; setelah itu booking kedaluwarsa otomatis dan jadwal dibuka kembali. Untuk instansi pemerintah, tersedia mekanisme LKPP / E-Katalog.'],
                ['Pembatalan & Refund', 'Permintaan pembatalan diajukan melalui WhatsApp atau chat dengan menyebutkan kode booking. Pengembalian dana mengikuti tabel di bawah dan diproses manual oleh admin.'],
                ['Penggunaan Ruangan', 'Pengguna wajib menjaga kebersihan, tidak melebihi kapasitas, dan tidak membawa barang terlarang. Kerusakan fasilitas menjadi tanggung jawab penyewa.'],
                ['Keterlambatan', 'Kelebihan waktu penggunaan dihitung per jam sesuai tarif ruangan dan bergantung pada ketersediaan jadwal berikutnya.'],
                ['Privasi Data', 'Data pribadi yang Anda berikan hanya dipakai untuk memproses reservasi dan tidak dibagikan ke pihak ketiga tanpa persetujuan.'],
            ] as $i => [$t, $d])
                <section class="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 class="font-bold text-ink">{{ $i + 1 }}. {{ $t }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $d }}</p>
                </section>
            @endforeach
        </div>
        <section class="mt-4 rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="font-bold text-ink">Kebijakan Refund</h2>
            <table class="mt-3 w-full text-left text-sm">
                <thead class="text-[11px] font-bold uppercase tracking-wide text-slate-500"><tr><th class="py-2">Waktu pembatalan</th><th class="py-2">Refund</th></tr></thead>
                <tbody class="divide-y divide-slate-100 text-slate-600">
                    <tr><td class="py-2">48 jam atau lebih sebelum jadwal sewa</td><td class="py-2 font-semibold">100%</td></tr>
                    <tr><td class="py-2">24 hingga 48 jam sebelum jadwal sewa</td><td class="py-2 font-semibold">50%</td></tr>
                    <tr><td class="py-2">Kurang dari 24 jam sebelum jadwal sewa</td><td class="py-2 font-semibold">0%</td></tr>
                </tbody>
            </table>
        </section>
    </div>
</div>
@endsection
