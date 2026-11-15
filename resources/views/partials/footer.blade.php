<footer class="mt-16 border-t border-zinc-200 bg-zinc-50">
    <div class="container-page grid gap-10 py-12 md:grid-cols-[1.4fr_1fr_1fr_1fr]">
        <div>
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-semibold tracking-tight">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white"><i data-lucide="building-2" class="h-5 w-5"></i></span>
                SpaceRent
            </a>
            <p class="mt-4 max-w-xs text-sm leading-relaxed text-zinc-600">Pesan ruang kerja, studio, dan event space di Jakarta. Jadwal real-time, harga jelas, pembayaran aman.</p>
        </div>
        <div>
            <h2 class="text-sm font-semibold">Jelajahi</h2>
            <ul class="mt-4 space-y-2.5 text-sm text-zinc-600">
                @foreach (\App\Models\Space::CATEGORIES as $slug => $c)
                    <li><a class="hover:text-zinc-900 hover:underline" href="{{ route('home', ['category' => $slug]) }}#ruangan">{{ $c['label'] }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="text-sm font-semibold">Bantuan</h2>
            <ul class="mt-4 space-y-2.5 text-sm text-zinc-600">
                <li><a class="hover:text-zinc-900 hover:underline" href="{{ route('faq') }}">Pertanyaan umum</a></li>
                <li><a class="hover:text-zinc-900 hover:underline" href="{{ route('terms') }}">Syarat dan kebijakan batal</a></li>
                <li><a class="hover:text-zinc-900 hover:underline" href="{{ route('contact') }}">Hubungi kami</a></li>
                <li><a class="hover:text-zinc-900 hover:underline" href="{{ route('register', ['role' => 'seller']) }}">Daftarkan ruangan Anda</a></li>
            </ul>
        </div>
        <div>
            <h2 class="text-sm font-semibold">Kontak</h2>
            <ul class="mt-4 space-y-2.5 text-sm text-zinc-600">
                <li>{{ config('spacerent.hotline') }}</li>
                <li>{{ config('spacerent.email') }}</li>
                <li><a class="font-medium text-zinc-900 hover:underline" href="https://wa.me/{{ config('spacerent.whatsapp') }}" target="_blank" rel="noopener">Chat WhatsApp</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-zinc-200 py-5 text-center text-xs text-zinc-600">&copy; {{ date('Y') }} SpaceRent. Seluruh hak cipta dilindungi.</div>
</footer>
