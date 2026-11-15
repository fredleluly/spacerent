{{-- Widget bantuan (chatbot) kanan bawah. Tampil untuk semua pengunjung, termasuk anonim. Logika di resources/js/chatbot.js. --}}
<div id="sr-chatbot" class="sr-chat" hidden
     data-config-url="{{ route('chatbot.config') }}"
     data-message-url="{{ route('chatbot.message') }}"
     data-csrf="{{ csrf_token() }}"
     data-login-url="{{ route('login') }}"
     data-user="{{ auth()->id() ?? 'guest' }}">

    <p class="sr-chat__teaser" data-teaser hidden>
        <span>Butuh bantuan mencari ruangan?</span>
        <button type="button" data-teaser-close aria-label="Tutup sapaan">&times;</button>
    </p>

    <section class="sr-chat__panel" data-panel role="dialog" aria-modal="false" aria-labelledby="sr-chat-title" hidden>
        <header class="sr-chat__head">
            <span class="sr-chat__avatar" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4z"/><path d="M19 15l.8 1.9 1.9.8-1.9.8L19 20.5l-.8-1.9-1.9-.8 1.9-.8z"/></svg>
            </span>
            <div class="sr-chat__title">
                <h2 id="sr-chat-title">Asisten SpaceRent</h2>
                <p><span class="sr-chat__dot" aria-hidden="true"></span> Online · <span data-mode>menjawab dari data kami</span></p>
            </div>
            <button type="button" class="sr-chat__icon" data-reset title="Mulai ulang percakapan" aria-label="Mulai ulang percakapan">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>
            </button>
            <button type="button" class="sr-chat__icon" data-close aria-label="Tutup asisten">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="sr-chat__log" data-log role="log" aria-live="polite" aria-relevant="additions" tabindex="0"></div>

        <div class="sr-chat__chips" data-chips></div>

        <form class="sr-chat__form" data-form autocomplete="off">
            <label for="sr-chat-input" class="sr-only">Tulis pertanyaan</label>
            <textarea id="sr-chat-input" data-input rows="1" placeholder="Tanya ruangan, harga, jadwal..." maxlength="500"></textarea>
            <button type="submit" data-send aria-label="Kirim pesan">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/></svg>
            </button>
        </form>
        <p class="sr-chat__foot">Asisten otomatis. Untuk pesanan dan pembayaran, ikuti tombol di halaman ruangan.</p>
    </section>

    <button type="button" class="sr-chat__bubble" data-toggle aria-expanded="false" aria-label="Buka asisten bantuan">
        <svg class="sr-chat__ico-open" viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.5-4.6A8 8 0 1 1 21 12z"/><path d="M8.5 12h.01M12 12h.01M15.5 12h.01" stroke-width="2.6"/></svg>
        <svg class="sr-chat__ico-close" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
</div>
