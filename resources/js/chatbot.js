// Widget chatbot SpaceRent (kanan bawah). Berbicara dengan /chatbot/config dan /chatbot/message.
import '../css/chatbot.css';

const STORE = 'sr-chat-v1';
const LOC = 'sr-chat-loc';
const TEASER = 'sr-chat-teaser';

const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/** Tautan hanya ke origin sendiri atau WhatsApp; selain itu ditampilkan sebagai teks. */
function safeHref(url) {
    try {
        const u = new URL(url, location.origin);
        if (u.origin === location.origin || u.hostname === 'wa.me') return u.href;
    } catch (e) { /* abaikan */ }
    return null;
}

/** Markdown ringan yang aman: HTML di-escape dulu, baru **tebal**, daftar "- ", dan tautan tepercaya. */
function md(text) {
    const lines = esc(text).split(/\r?\n/);
    const out = [];
    let list = false;
    const inline = (s) => s
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/(https?:\/\/[^\s<]+[^\s<.,;:!?)])/g, (m) => {
            const href = safeHref(m.replace(/&amp;/g, '&'));
            return href ? `<a href="${esc(href)}" rel="noopener">${m}</a>` : m;
        });
    for (const raw of lines) {
        const li = raw.match(/^\s*[-*]\s+(.*)$/);
        if (li) {
            if (!list) { out.push('<ul>'); list = true; }
            out.push(`<li>${inline(li[1])}</li>`);
            continue;
        }
        if (list) { out.push('</ul>'); list = false; }
        if (raw.trim() !== '') out.push(`<p>${inline(raw)}</p>`);
    }
    if (list) out.push('</ul>');
    return out.join('');
}

function init() {
    const root = document.getElementById('sr-chatbot');
    if (!root) return;

    const $ = (sel) => root.querySelector(sel);
    const panel = $('[data-panel]'); const log = $('[data-log]'); const form = $('[data-form]');
    const input = $('[data-input]'); const sendBtn = $('[data-send]'); const chips = $('[data-chips]');
    const toggle = $('[data-toggle]'); const teaser = $('[data-teaser]'); const modeEl = $('[data-mode]');

    const cfg = { enabled: true, welcome: '', suggestions: [], max_chars: 500, provider: 'rules' };
    let messages = [];   // {role:'user'|'bot', text, spaces?, actions?, kind?}
    let busy = false;
    let loc = null;
    let lastQuestion = '';
    let loaded = false;

    try { loc = JSON.parse(sessionStorage.getItem(LOC) || 'null'); } catch (e) { loc = null; }
    try {
        const saved = JSON.parse(sessionStorage.getItem(STORE) || 'null');
        if (saved && saved.user === root.dataset.user && Array.isArray(saved.messages)) messages = saved.messages;
    } catch (e) { messages = []; }

    if (document.querySelector('.fixed.inset-x-0.bottom-0')) root.classList.add('has-bar');

    const save = () => {
        try { sessionStorage.setItem(STORE, JSON.stringify({ user: root.dataset.user, messages: messages.slice(-30) })); } catch (e) { /* mode privat */ }
    };
    const scroll = () => { log.scrollTop = log.scrollHeight; };

    function card(s) {
        const a = document.createElement('a');
        a.className = 'sr-card';
        a.href = safeHref(s.url) || '#';
        const tags = [];
        if (s.distance_km != null) tags.push(`<span class="sr-tag">${esc(String(s.distance_km).replace('.', ','))} km</span>`);
        if (s.rating) tags.push(`<span class="sr-tag sr-tag--n">★ ${esc(Number(s.rating).toFixed(1).replace('.', ','))}</span>`);
        tags.push(`<span class="sr-tag sr-tag--n">${esc(s.capacity)} orang</span>`);
        a.innerHTML = `<img src="${esc(s.image)}" alt="" loading="lazy" referrerpolicy="no-referrer">
            <div class="sr-card__b"><p class="sr-card__n">${esc(s.name)}</p><p class="sr-card__m">${esc(s.district || '')}${s.area ? ', ' + esc(s.area) : ''}</p>
            <p class="sr-card__p">${esc(s.price)} <small>/ ${esc(s.unit)}</small></p><div class="sr-card__tags">${tags.join('')}</div></div>`;
        return a;
    }

    function actionButtons(actions) {
        const wrap = document.createElement('div');
        wrap.className = 'sr-actions';
        for (const a of actions || []) {
            if (a.type === 'request_location') {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'sr-btn sr-btn--p'; b.textContent = 'Bagikan lokasi saya';
                b.addEventListener('click', () => shareLocation(b));
                wrap.appendChild(b);
            } else if (a.type === 'login' && safeHref(a.url)) {
                const l = document.createElement('a');
                l.className = 'sr-btn sr-btn--p'; l.href = safeHref(a.url); l.textContent = 'Masuk ke akun';
                wrap.appendChild(l);
            } else if (a.type === 'handoff') {
                if (a.whatsapp && safeHref(a.whatsapp)) {
                    const w = document.createElement('a');
                    w.className = 'sr-btn'; w.href = safeHref(a.whatsapp); w.target = '_blank'; w.rel = 'noopener'; w.textContent = 'Chat WhatsApp';
                    wrap.appendChild(w);
                }
                if (a.contact && safeHref(a.contact)) {
                    const c = document.createElement('a');
                    c.className = 'sr-btn'; c.href = safeHref(a.contact); c.textContent = 'Hubungi kami';
                    wrap.appendChild(c);
                }
            }
        }
        return wrap.children.length ? wrap : null;
    }

    function draw(m) {
        const el = document.createElement('div');
        if (m.role === 'user') {
            el.className = 'sr-msg sr-msg--user';
            el.textContent = m.text;
            log.appendChild(el);
            return;
        }
        el.className = 'sr-msg ' + (m.kind === 'error' ? 'sr-msg--error' : m.kind === 'note' ? 'sr-msg--note' : 'sr-msg--bot');
        el.innerHTML = m.kind === 'note' ? esc(m.text) : md(m.text);
        log.appendChild(el);
        if (m.spaces && m.spaces.length) {
            const row = document.createElement('div');
            row.className = 'sr-cards';
            m.spaces.forEach((s) => row.appendChild(card(s)));
            log.appendChild(row);
        }
        const btns = m.kind === 'error' && m.retry ? retryButton() : actionButtons(m.actions);
        if (btns) log.appendChild(btns);
    }

    function retryButton() {
        const wrap = document.createElement('div');
        wrap.className = 'sr-actions';
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'sr-btn'; b.textContent = 'Coba lagi';
        b.addEventListener('click', () => { if (lastQuestion && !busy) ask(lastQuestion, { silent: true }); });
        wrap.appendChild(b);
        return wrap;
    }

    function render() {
        log.innerHTML = '';
        messages.forEach(draw);
        scroll();
    }

    function setBusy(v) {
        busy = v;
        sendBtn.disabled = v;
        const t = log.querySelector('.sr-msg--typing');
        if (v && !t) {
            const el = document.createElement('div');
            el.className = 'sr-msg sr-msg--bot sr-msg--typing';
            el.setAttribute('aria-label', 'Asisten sedang mengetik');
            el.innerHTML = '<span class="sr-typing"><i></i><i></i><i></i></span>';
            log.appendChild(el); scroll();
        } else if (!v && t) {
            t.remove();
        }
    }

    function push(m) { messages.push(m); draw(m); scroll(); save(); }

    function history() {
        return messages.filter((m) => m.kind !== 'note' && m.kind !== 'error' && m.text)
            .slice(-12).map((m) => ({ role: m.role === 'user' ? 'user' : 'assistant', content: String(m.text).slice(0, 800) }));
    }

    async function ask(text, opts = {}) {
        text = text.trim();
        if (!text || busy) return;
        lastQuestion = text;
        if (opts.silent) {
            // coba lagi / kirim ulang setelah lokasi: jangan menduplikasi gelembung pengguna; buang pesan galat terakhir
            if (messages.length && messages[messages.length - 1].kind === 'error') { messages.pop(); render(); }
        } else {
            push({ role: 'user', text });
        }
        chips.innerHTML = '';
        setBusy(true);

        // Server menambahkan pesan terbaru sendiri; jangan kirim dua kali.
        const hist = history();
        if (hist.length && hist[hist.length - 1].role === 'user' && hist[hist.length - 1].content === text.slice(0, 800)) hist.pop();
        const body = { message: text, history: hist };
        if (loc) { body.lat = loc.lat; body.lng = loc.lng; }

        try {
            const res = await fetch(root.dataset.messageUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            let data = {};
            try { data = await res.json(); } catch (e) { /* bukan JSON */ }
            setBusy(false);

            if (res.ok) {
                if (data.provider) setMode(data.provider, data.fallback);
                push({ role: 'bot', text: data.answer || 'Maaf, saya belum punya jawaban.', spaces: data.spaces || [], actions: data.actions || [] });
            } else if (res.status === 419) {
                push({ role: 'bot', kind: 'error', retry: false, text: 'Sesi halaman kedaluwarsa. Muat ulang halaman lalu coba lagi.' });
            } else if (res.status === 422) {
                const first = data.errors && Object.values(data.errors)[0];
                push({ role: 'bot', kind: 'error', text: (first && first[0]) || 'Pesan tidak valid.' });
            } else if (res.status === 429) {
                push({ role: 'bot', kind: 'error', retry: true, text: data.answer || 'Terlalu banyak pesan. Tunggu sebentar lalu coba lagi.' });
            } else if (res.status === 503) {
                push({ role: 'bot', kind: 'error', text: data.answer || 'Asisten sedang tidak aktif.' });
            } else {
                push({ role: 'bot', kind: 'error', retry: true, text: 'Terjadi gangguan di server. Coba lagi sebentar lagi.' });
            }
        } catch (e) {
            setBusy(false);
            push({ role: 'bot', kind: 'error', retry: true, text: 'Tidak dapat terhubung. Periksa koneksi internet Anda.' });
        }
        input.focus();
    }

    function shareLocation(btn) {
        if (!navigator.geolocation) {
            push({ role: 'bot', kind: 'note', text: 'Peramban ini tidak mendukung lokasi. Sebutkan kawasan Anda, mis. Kemang atau Kuningan.' });
            return;
        }
        btn.disabled = true; btn.textContent = 'Mencari lokasi...';
        navigator.geolocation.getCurrentPosition((pos) => {
            loc = { lat: +pos.coords.latitude.toFixed(5), lng: +pos.coords.longitude.toFixed(5) };
            try { sessionStorage.setItem(LOC, JSON.stringify(loc)); } catch (e) { /* abaikan */ }
            btn.remove();
            push({ role: 'bot', kind: 'note', text: 'Lokasi dibagikan. Dipakai hanya untuk menghitung jarak di percakapan ini.' });
            ask(lastQuestion || 'Cari ruangan terdekat', { silent: true });
        }, (err) => {
            btn.disabled = false; btn.textContent = 'Bagikan lokasi saya';
            push({ role: 'bot', kind: 'note', text: err && err.code === 1
                ? 'Izin lokasi ditolak. Tidak apa-apa: sebutkan kawasan Anda (mis. Kemang, Kuningan, Jakarta Selatan) dan saya carikan.'
                : 'Lokasi belum tersedia. Coba lagi atau sebutkan kawasan Anda.' });
        }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
    }

    function setMode(provider, fallback) {
        modeEl.textContent = fallback ? 'mode cadangan' : provider === 'gemini' ? 'ditenagai AI Gemini' : provider === 'ollama' ? 'ditenagai AI lokal' : 'menjawab dari data kami';
    }

    function showChips() {
        chips.innerHTML = '';
        if (messages.length > 1 || busy) return;
        (cfg.suggestions || []).forEach((s) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'sr-chip'; b.textContent = s;
            b.addEventListener('click', () => ask(s));
            chips.appendChild(b);
        });
    }

    function start() {
        if (!messages.length) {
            const hello = cfg.welcome || 'Halo! Ada yang bisa saya bantu?';
            messages.push({ role: 'bot', text: cfg.authenticated && cfg.user_name ? hello.replace(/^Halo!/, `Halo, ${cfg.user_name}!`) : hello });
        }
        render(); showChips();
    }

    async function loadConfig() {
        if (loaded) return;
        try {
            const res = await fetch(root.dataset.configUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            Object.assign(cfg, await res.json());
        } catch (e) { /* pakai bawaan */ }
        loaded = true;
        if (!cfg.enabled) { root.remove(); return false; }
        input.maxLength = cfg.max_chars || 500;
        setMode(cfg.provider);
        return true;
    }

    async function open() {
        if ((await loadConfig()) === false) return;
        root.classList.add('is-open');
        panel.hidden = false; teaser.hidden = true;
        toggle.setAttribute('aria-expanded', 'true'); toggle.setAttribute('aria-label', 'Tutup asisten bantuan');
        if (!log.children.length) start();
        try { sessionStorage.setItem(TEASER, '1'); } catch (e) { /* abaikan */ }
        setTimeout(() => input.focus(), 50);
        scroll();
    }

    function close() {
        root.classList.remove('is-open');
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false'); toggle.setAttribute('aria-label', 'Buka asisten bantuan');
        toggle.focus();
    }

    toggle.addEventListener('click', () => (panel.hidden ? open() : close()));
    $('[data-close]').addEventListener('click', close);
    $('[data-teaser-close]').addEventListener('click', () => { teaser.hidden = true; try { sessionStorage.setItem(TEASER, '1'); } catch (e) { /* abaikan */ } });
    teaser.addEventListener('click', (e) => { if (!e.target.closest('[data-teaser-close]')) open(); });
    $('[data-reset]').addEventListener('click', () => {
        messages = []; lastQuestion = ''; try { sessionStorage.removeItem(STORE); } catch (e) { /* abaikan */ }
        start();
    });
    root.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) close(); });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const v = input.value;
        input.value = ''; input.style.height = 'auto';
        ask(v);
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); }
    });
    input.addEventListener('input', () => { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 110) + 'px'; });

    // Tampilkan bubble hanya bila chatbot aktif.
    root.hidden = false;
    loadConfig().then((ok) => {
        if (ok === false) return;
        let seen = false;
        try { seen = sessionStorage.getItem(TEASER) === '1'; } catch (e) { /* abaikan */ }
        if (!seen && !window.matchMedia('(max-width: 520px)').matches) setTimeout(() => { if (panel.hidden) teaser.hidden = false; }, 4500);
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
