// Peta Leaflet (OpenStreetMap): penjelajah ruangan ([data-space-map]), pemilih lokasi penjual ([data-location-picker]),
// dan peta kecil di halaman ruangan ([data-static-map]).
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../css/map.css';

const JAKARTA = [-6.2088, 106.8456];
const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';

const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };

function baseMap(node, opts = {}) {
    const map = L.map(node, { zoomControl: true, scrollWheelZoom: false, attributionControl: true, ...opts });
    L.tileLayer(TILE_URL, { maxZoom: 19, attribution: ATTR }).addTo(map);
    // Gulir roda mouse hanya memperbesar peta setelah peta diklik, supaya halaman tetap bisa digulir.
    map.on('click focus', () => map.scrollWheelZoom.enable());
    map.on('blur mouseout', () => map.scrollWheelZoom.disable());
    setTimeout(() => map.invalidateSize(), 150);
    return map;
}

/** "Rp 350.000" -> "Rp 350rb", "Rp 2.500.000" -> "Rp 2,5jt" */
function compactPrice(label) {
    const n = parseInt(String(label).replace(/\D/g, ''), 10);
    if (!Number.isFinite(n)) return label;
    if (n >= 1e6) return 'Rp ' + (n / 1e6).toFixed(n % 1e6 ? 1 : 0).replace('.', ',') + 'jt';
    if (n >= 1e3) return 'Rp ' + Math.round(n / 1e3) + 'rb';
    return 'Rp ' + n;
}

const km = (v) => String(v).replace('.', ',') + ' km';

function popup(s) {
    const wrap = el('div', 'sr-pop');
    const img = el('img'); img.src = s.image; img.alt = ''; img.loading = 'lazy'; img.referrerPolicy = 'no-referrer';
    const body = el('div', 'sr-pop__b');
    const a = el('a', 'sr-pop__n', s.name); a.href = s.url;
    const meta = [s.category, s.district, s.capacity ? s.capacity + ' orang' : null, s.rating ? '★ ' + s.rating.toFixed(1).replace('.', ',') : null, s.distance_km != null ? km(s.distance_km) : null].filter(Boolean).join(' · ');
    const price = el('p', 'sr-pop__p', s.price + ' '); price.appendChild(el('small', '', '/ ' + s.unit));
    const go = el('a', 'sr-pop__go', 'Lihat ruangan'); go.href = s.url;
    body.append(a, el('p', 'sr-pop__m', meta), price, go);
    wrap.append(img, body);
    return wrap;
}

function pinIcon(s) {
    return L.divIcon({ className: 'sr-pin-wrap', html: `<span class="sr-pin">${compactPrice(s.price).replace(/[<>&]/g, '')}</span>`, iconSize: [0, 0] });
}

// ------------------------------------------------------------------ penjelajah

function initExplorer(root) {
    const mapNode = root.querySelector('[data-map]');
    const list = root.querySelector('[data-list]');
    const qInput = root.querySelector('[data-q]');
    const cat = root.querySelector('[data-cat]');
    const nearBtn = root.querySelector('[data-near]');
    const status = root.querySelector('[data-status]');
    if (!mapNode) return;

    let map; let layer; let youMarker; let userLoc = null; let ctrl; let timer;
    const pins = new Map();

    const say = (t) => { if (status) status.textContent = t; };

    async function load(opts = {}) {
        const params = new URLSearchParams();
        if (qInput && qInput.value.trim()) params.set('q', qInput.value.trim());
        if (cat && cat.value) params.set('category', cat.value);
        if (userLoc) { params.set('lat', userLoc.lat); params.set('lng', userLoc.lng); }
        params.set('limit', userLoc ? '12' : '60');
        if (ctrl) ctrl.abort();
        ctrl = new AbortController();
        say('Memuat ruangan...');
        try {
            const res = await fetch(root.dataset.url + '?' + params, { headers: { Accept: 'application/json' }, signal: ctrl.signal });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            render(data.spaces || [], opts);
        } catch (e) {
            if (e.name !== 'AbortError') say('Peta belum dapat dimuat. Coba lagi sebentar lagi.');
        }
    }

    function render(spaces, opts) {
        layer.clearLayers(); pins.clear(); list.innerHTML = '';
        spaces.forEach((s) => {
            if (s.lat == null || s.lng == null) return;
            const m = L.marker([s.lat, s.lng], { icon: pinIcon(s), title: s.name, riseOnHover: true }).bindPopup(popup(s), { maxWidth: 240, autoPanPadding: [30, 30] });
            m.on('popupopen', () => m.getElement()?.firstChild?.classList.add('is-active'));
            m.on('popupclose', () => m.getElement()?.firstChild?.classList.remove('is-active'));
            m.addTo(layer); pins.set(s.slug, m);

            const li = el('li');
            const b = el('button', 'group flex w-full items-start gap-3 rounded-xl p-2.5 text-left transition hover:bg-zinc-100 focus-visible:bg-zinc-100');
            b.type = 'button';
            const img = el('img', 'h-14 w-16 shrink-0 rounded-lg bg-zinc-100 object-cover'); img.src = s.image; img.alt = ''; img.loading = 'lazy'; img.referrerPolicy = 'no-referrer';
            const t = el('span', 'min-w-0 flex-1');
            t.append(el('span', 'block truncate text-sm font-semibold', s.name),
                el('span', 'block truncate text-xs text-zinc-600', [s.district, s.area].filter(Boolean).join(', ')),
                el('span', 'mt-0.5 block text-xs font-semibold text-zinc-900', s.price + ' / ' + s.unit));
            b.append(img, t);
            if (s.distance_km != null) b.append(el('span', 'shrink-0 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700', km(s.distance_km)));
            b.addEventListener('click', () => { map.setView([s.lat, s.lng], Math.max(map.getZoom(), 15), { animate: true }); m.openPopup(); mapNode.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); });
            li.appendChild(b); list.appendChild(li);
        });

        if (!pins.size) {
            list.appendChild(el('li', 'p-4 text-sm text-zinc-600', 'Tidak ada ruangan berkoordinat yang cocok. Coba kata kunci atau kategori lain.'));
            say('Tidak ada hasil.');
        } else {
            say(userLoc ? `${pins.size} ruangan terdekat dari lokasi Anda.` : `${pins.size} ruangan di peta.`);
        }

        const pts = [...pins.values()].map((m) => m.getLatLng());
        if (userLoc) pts.push([userLoc.lat, userLoc.lng]);
        if (pts.length && opts.fit !== false) map.fitBounds(L.latLngBounds(pts).pad(0.2), { maxZoom: 15, animate: !opts.first });
        else if (!pts.length && !userLoc) map.setView(JAKARTA, 11);
    }

    function locate() {
        if (!navigator.geolocation) { say('Peramban ini tidak mendukung lokasi. Cari berdasarkan kawasan di kotak pencarian.'); return; }
        nearBtn.disabled = true; say('Mencari lokasi Anda...');
        navigator.geolocation.getCurrentPosition((pos) => {
            userLoc = { lat: +pos.coords.latitude.toFixed(5), lng: +pos.coords.longitude.toFixed(5) };
            try { sessionStorage.setItem('sr-chat-loc', JSON.stringify(userLoc)); } catch (e) { /* abaikan */ }
            if (youMarker) youMarker.remove();
            youMarker = L.marker([userLoc.lat, userLoc.lng], { icon: L.divIcon({ className: '', html: '<div class="sr-you"></div>', iconSize: [0, 0] }), interactive: false, keyboard: false }).addTo(map);
            nearBtn.disabled = false; nearBtn.setAttribute('aria-pressed', 'true');
            load();
        }, (err) => {
            nearBtn.disabled = false;
            say(err && err.code === 1 ? 'Izin lokasi ditolak. Tidak apa-apa: ketik kawasan (mis. Kemang) di kotak pencarian.' : 'Lokasi belum tersedia. Coba lagi atau cari berdasarkan kawasan.');
        }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
    }

    function start() {
        map = baseMap(mapNode, { center: JAKARTA, zoom: 11 });
        layer = L.layerGroup().addTo(map);
        qInput?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 350); });
        qInput?.closest('form')?.addEventListener('submit', (e) => { e.preventDefault(); clearTimeout(timer); load(); });
        cat?.addEventListener('change', load);
        nearBtn?.addEventListener('click', locate);
        load({ first: true });
    }

    // Muat peta saat mendekati layar agar halaman awal tetap ringan.
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) { io.disconnect(); start(); } }, { rootMargin: '300px' });
        io.observe(root);
    } else {
        start();
    }
}

// ------------------------------------------------------------------ pemilih lokasi (penjual)

function initPicker(root) {
    const mapNode = root.querySelector('[data-map]');
    const latIn = root.querySelector('[data-lat]'); const lngIn = root.querySelector('[data-lng]'); const addrIn = root.querySelector('[data-address]');
    const searchIn = root.querySelector('[data-search]'); const searchBtn = root.querySelector('[data-search-btn]');
    const locateBtn = root.querySelector('[data-locate]'); const clearBtn = root.querySelector('[data-clear]');
    const results = root.querySelector('[data-results]'); const msg = root.querySelector('[data-msg]');
    if (!mapNode || !latIn || !lngIn) return;

    const say = (t, bad = false) => { if (msg) { msg.textContent = t; msg.className = 'mt-2 text-xs ' + (bad ? 'font-medium text-rose-700' : 'text-zinc-600'); } };
    const num = (v) => { const n = parseFloat(String(v).replace(',', '.')); return Number.isFinite(n) ? n : null; };

    const lat0 = num(latIn.value); const lng0 = num(lngIn.value);
    const map = baseMap(mapNode, { center: lat0 != null && lng0 != null ? [lat0, lng0] : JAKARTA, zoom: lat0 != null ? 16 : 11 });
    const icon = L.divIcon({ className: '', html: '<div class="sr-drop"></div>', iconSize: [0, 0] });
    let marker = null;

    function setPoint(lat, lng, opts = {}) {
        lat = +lat.toFixed(6); lng = +lng.toFixed(6);
        latIn.value = lat; lngIn.value = lng;
        if (!marker) {
            marker = L.marker([lat, lng], { icon, draggable: true, keyboard: true, title: 'Seret untuk memindahkan lokasi' }).addTo(map);
            marker.on('dragend', () => { const p = marker.getLatLng(); setPoint(p.lat, p.lng, { reverse: true, keepView: true }); });
        } else {
            marker.setLatLng([lat, lng]);
        }
        if (!opts.keepView) map.setView([lat, lng], Math.max(map.getZoom(), 16));
        say('Lokasi ditetapkan. Seret penanda atau klik peta untuk memindahkan.');
        if (opts.reverse) reverse(lat, lng);
    }

    async function reverse(lat, lng) {
        if (!addrIn || addrIn.value.trim() !== '' && addrIn.dataset.auto !== '1') return; // jangan menimpa alamat yang diketik penjual
        try {
            const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=id&zoom=18&lat=${lat}&lon=${lng}`, { headers: { Accept: 'application/json' } });
            const d = await r.json();
            if (d && d.display_name) { addrIn.value = String(d.display_name).slice(0, 250); addrIn.dataset.auto = '1'; }
        } catch (e) { /* alamat boleh diisi manual */ }
    }

    async function search() {
        const q = searchIn.value.trim();
        if (q.length < 3) { say('Ketik minimal 3 huruf (nama gedung atau alamat).', true); return; }
        results.innerHTML = ''; say('Mencari alamat...');
        try {
            const r = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&countrycodes=id&accept-language=id&q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
            const items = await r.json();
            if (!items.length) { say('Alamat tidak ditemukan. Coba nama gedung/jalan yang lebih umum, atau klik langsung di peta.', true); return; }
            say('Pilih hasil yang sesuai:');
            items.forEach((it) => {
                const li = el('li'); const b = el('button', 'block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-zinc-100', it.display_name); b.type = 'button';
                b.addEventListener('click', () => {
                    results.innerHTML = '';
                    setPoint(parseFloat(it.lat), parseFloat(it.lon));
                    if (addrIn && (addrIn.value.trim() === '' || addrIn.dataset.auto === '1')) { addrIn.value = it.display_name.slice(0, 250); addrIn.dataset.auto = '1'; }
                });
                li.appendChild(b); results.appendChild(li);
            });
        } catch (e) { say('Pencarian alamat sedang tidak tersedia. Klik langsung di peta untuk menetapkan lokasi.', true); }
    }

    map.on('click', (e) => setPoint(e.latlng.lat, e.latlng.lng, { reverse: true, keepView: true }));
    searchBtn?.addEventListener('click', search);
    searchIn?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); search(); } });
    addrIn?.addEventListener('input', () => { addrIn.dataset.auto = '0'; });

    locateBtn?.addEventListener('click', () => {
        if (!navigator.geolocation) { say('Peramban tidak mendukung lokasi.', true); return; }
        say('Mencari lokasi Anda...');
        navigator.geolocation.getCurrentPosition((p) => setPoint(p.coords.latitude, p.coords.longitude, { reverse: true }),
            () => say('Izin lokasi ditolak. Cari alamat atau klik peta.', true), { timeout: 10000 });
    });

    clearBtn?.addEventListener('click', () => {
        if (marker) { marker.remove(); marker = null; }
        latIn.value = ''; lngIn.value = ''; say('Lokasi dihapus. Ruangan tidak akan muncul di peta sampai lokasi ditetapkan.');
    });

    const onManual = () => { const a = num(latIn.value); const b = num(lngIn.value); if (a != null && b != null && Math.abs(a) <= 90 && Math.abs(b) <= 180) setPoint(a, b, { keepView: false }); };
    latIn.addEventListener('change', onManual); lngIn.addEventListener('change', onManual);

    if (lat0 != null && lng0 != null) setPoint(lat0, lng0, { keepView: true });
    else say('Klik peta, cari alamat, atau pakai lokasi Anda untuk menetapkan titik ruangan.');
}

// ------------------------------------------------------------------ peta kecil (halaman ruangan)

function initStatic(root) {
    const lat = parseFloat(root.dataset.lat); const lng = parseFloat(root.dataset.lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    const start = () => {
        const map = baseMap(root, { center: [lat, lng], zoom: 16 });
        L.marker([lat, lng], { icon: L.divIcon({ className: '', html: '<div class="sr-drop"></div>', iconSize: [0, 0] }), interactive: false, keyboard: false }).addTo(map);
    };
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((e) => { if (e.some((x) => x.isIntersecting)) { io.disconnect(); start(); } }, { rootMargin: '300px' });
        io.observe(root);
    } else start();
}

function init() {
    document.querySelectorAll('[data-space-map]').forEach(initExplorer);
    document.querySelectorAll('[data-location-picker]').forEach(initPicker);
    document.querySelectorAll('[data-static-map]').forEach(initStatic);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
