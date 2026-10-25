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
                ['Pembayaran', 'Pembayaran dilakukan melalui transfer bank atau virtual account. Untuk instansi pemerintah, tersedia pembayaran melalui mekanisme LKPP / E-Katalog.'],
                ['Pembatalan & Jadwal Ulang', 'Pembatalan maksimal H-2 sebelum jadwal mendapat pengembalian dana penuh. Pembatalan H-1 dikenakan biaya 50%. Pembatalan di hari H tidak dapat dikembalikan.'],
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
    </div>
</div>
@endsection
