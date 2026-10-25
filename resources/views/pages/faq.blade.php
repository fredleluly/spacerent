@extends('layouts.app')

@section('title', 'FAQ - SpaceRent')

@section('content')
<div class="bg-slate-50 py-14">
    <div class="mx-auto max-w-3xl px-4">
        <p class="text-[11px] font-bold uppercase tracking-widest text-brand-600">Bantuan</p>
        <h1 class="mt-2 text-3xl font-extrabold">Pertanyaan yang Sering Diajukan</h1>
        <div class="mt-8 space-y-3">
            @foreach ([
                ['Bagaimana cara memesan ruangan?', 'Pilih ruangan di beranda, isi form booking pada halaman detail, lalu konfirmasi kode booking Anda ke CS melalui WhatsApp.'],
                ['Apakah harga sudah termasuk pajak?', 'Harga yang tertera belum termasuk PPN 11%. Invoice resmi dan faktur pajak dapat diminta saat konfirmasi.'],
                ['Apakah bisa menyewa untuk jam di luar jam kerja?', 'Bisa. Layanan reservasi kami buka 24/7 dan banyak lokasi menyediakan akses di luar jam kerja.'],
                ['Apakah tersedia catering?', 'Tersedia paket snack, makan siang, dan coffee break dari mitra kami. Sebutkan kebutuhan Anda di kolom catatan.'],
                ['Bisakah menggunakan skema LKPP / E-Katalog?', 'Ya, SpaceRent siap melayani pengadaan instansi melalui LKPP / E-Katalog. Hubungi CS untuk dokumen pendukung.'],
                ['Bagaimana jika ingin membatalkan?', 'Silakan hubungi CS dengan menyebutkan kode booking. Ketentuan pengembalian dana mengikuti halaman Syarat & Ketentuan.'],
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
