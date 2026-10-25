@php $wa = config('spacerent.whatsapp'); @endphp
<footer class="bg-brand-900 text-slate-300">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 md:grid-cols-4">
        <div class="md:col-span-2">
            <div class="flex items-center gap-2.5">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white"><i data-lucide="building" class="h-5 w-5"></i></span>
                <span class="text-lg font-extrabold text-white">Space<span class="text-sky-400">Rent</span></span>
            </div>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-400">
                Penyedia meeting room, private office, coworking, studio podcast, dan event venue siap pakai di seluruh titik strategis Jakarta dengan harga transparan.
            </p>
        </div>
        <div>
            <h4 class="text-sm font-bold uppercase tracking-wider text-white">Layanan</h4>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach (\App\Models\Space::CATEGORIES as $slug => $c)
                    <li><a class="hover:text-white" href="{{ route('home', ['category' => $slug]) }}#ruangan">{{ $c['label'] }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h4 class="text-sm font-bold uppercase tracking-wider text-white">Kontak</h4>
            <ul class="mt-4 space-y-3 text-sm">
                <li class="flex gap-2"><i data-lucide="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-sky-400"></i>{{ config('spacerent.address') }}</li>
                <li class="flex gap-2"><i data-lucide="phone" class="mt-0.5 h-4 w-4 shrink-0 text-sky-400"></i>{{ config('spacerent.hotline') }}</li>
                <li class="flex gap-2"><i data-lucide="mail" class="mt-0.5 h-4 w-4 shrink-0 text-sky-400"></i>{{ config('spacerent.email') }}</li>
            </ul>
            <div class="mt-4 flex gap-4 text-sm">
                <a href="{{ route('terms') }}" class="hover:text-white">Syarat &amp; Ketentuan</a>
                <a href="{{ route('faq') }}" class="hover:text-white">FAQ</a>
            </div>
        </div>
    </div>
    <div class="border-t border-white/10 py-5 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} SpaceRent. Seluruh hak cipta dilindungi.
    </div>
</footer>

<a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" aria-label="Chat WhatsApp"
   class="fixed bottom-5 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-500/40 transition hover:scale-105 hover:bg-emerald-600">
    <i data-lucide="message-circle" class="h-6 w-6"></i>
</a>
