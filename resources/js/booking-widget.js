// Widget pemesanan: kalender + pemilih slot + rincian harga. Server tetap menjadi sumber kebenaran.

const ID_MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const rupiah = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const parse = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
const fmtLong = (d) => d.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

function init(root) {
    const cfg = JSON.parse(root.dataset.config);
    const $ = (r) => root.querySelector(`[data-role="${r}"]`);
    const els = {
        cal: $('calendar'), label: $('month-label'), prev: $('prev'), next: $('next'), slots: $('slots'), slotsWrap: $('slots-wrap'),
        dur: $('duration'), minus: $('dur-minus'), plus: $('dur-plus'), durLabel: $('dur-label'), price: $('price'), submit: $('submit'),
        date: $('date-input'), start: $('start-input'), summary: $('summary'), hint: $('hint'),
    };
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const lastDay = new Date(today); lastDay.setDate(lastDay.getDate() + cfg.maxAdvance);
    const state = {
        month: new Date(today.getFullYear(), today.getMonth(), 1),
        date: cfg.old.date || '', start: cfg.old.start || '', duration: Number(cfg.old.duration) || cfg.minDuration,
        blocked: new Set(), slots: [], loading: false,
    };
    const isHour = cfg.unit === 'jam';

    const endDate = () => {
        if (!state.date) return null;
        const d = parse(state.date);
        if (cfg.unit === 'bulan') { const day = d.getDate(); d.setMonth(d.getMonth() + state.duration, 1); const max = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate(); d.setDate(Math.min(day, max)); return d; }
        d.setDate(d.getDate() + (cfg.unit === 'hari' ? state.duration : 0));
        return d;
    };

    async function load() {
        if (isHour && !state.date) { state.slots = []; renderSlots(); return; }
        state.loading = true; renderSlots();
        const q = new URLSearchParams({ date: state.date || ymd(today), duration: state.duration });
        try {
            const res = await fetch(`${cfg.url}?${q}`, { headers: { Accept: 'application/json' } });
            if (res.ok) {
                const j = await res.json();
                state.blocked = new Set(j.blocked || []);
                state.slots = j.slots || [];
                if (isHour && state.start && !state.slots.some((s) => s.time === state.start && s.available)) state.start = '';
            }
        } catch { /* jaringan: biarkan validasi server yang memutuskan */ }
        state.loading = false;
        renderCalendar(); renderSlots(); renderSummary();
    }

    function disabledDay(d) {
        if (d < today || d > lastDay) return true;
        if (!cfg.openDays.includes(d.getDay())) return true;
        return state.blocked.has(ymd(d));
    }

    function renderCalendar() {
        els.label.textContent = `${ID_MONTHS[state.month.getMonth()]} ${state.month.getFullYear()}`;
        els.prev.disabled = state.month <= new Date(today.getFullYear(), today.getMonth(), 1);
        els.next.disabled = new Date(state.month.getFullYear(), state.month.getMonth() + 1, 1) > lastDay;
        const first = new Date(state.month);
        const offset = (first.getDay() + 6) % 7;
        const days = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
        let html = '';
        for (let i = 0; i < offset; i++) html += '<span></span>';
        const end = endDate();
        for (let n = 1; n <= days; n++) {
            const d = new Date(first.getFullYear(), first.getMonth(), n);
            const key = ymd(d);
            const sel = key === state.date;
            const inRange = !isHour && state.date && end && d > parse(state.date) && d < end;
            html += `<button type="button" class="cal-day ${sel ? 'is-selected' : ''} ${inRange ? 'is-range' : ''} ${key === ymd(today) ? 'is-today' : ''}" data-day="${key}" ${disabledDay(d) ? 'disabled' : ''} aria-label="${fmtLong(d)}" aria-pressed="${sel}">${n}</button>`;
        }
        els.cal.innerHTML = html;
    }

    function renderSlots() {
        if (!els.slots) return;
        els.slotsWrap.hidden = !isHour || !state.date;
        if (!isHour) return;
        if (state.loading) { els.slots.innerHTML = Array.from({ length: 8 }, () => '<div class="skeleton h-10"></div>').join(''); return; }
        if (!state.slots.length) { els.slots.innerHTML = '<p class="col-span-full rounded-xl bg-zinc-50 p-3 text-sm text-zinc-600">Tidak ada jam tersedia untuk tanggal dan durasi ini. Coba tanggal lain atau kurangi durasi.</p>'; return; }
        els.slots.innerHTML = state.slots.map((s) => `<button type="button" class="slot ${s.time === state.start ? 'is-selected' : ''}" data-time="${s.time}" ${s.available ? '' : 'disabled'}>${s.time}</button>`).join('');
    }

    function renderSummary() {
        const ready = state.date && (!isHour || state.start);
        els.date.value = state.date; els.start.value = state.start;
        els.dur.value = state.duration;
        els.durLabel.textContent = `${state.duration} ${cfg.unit}`;
        els.minus.disabled = state.duration <= cfg.minDuration;
        els.plus.disabled = state.duration >= cfg.maxDuration;

        const sub = cfg.price * state.duration;
        const service = Math.round((sub + cfg.cleaningFee) * cfg.servicePct / 100);
        const total = sub + cfg.cleaningFee + service;
        const rows = [[`${rupiah(cfg.price)} x ${state.duration} ${cfg.unit}`, rupiah(sub)]];
        if (cfg.cleaningFee) rows.push(['Biaya kebersihan', rupiah(cfg.cleaningFee)]);
        if (service) rows.push(['Biaya layanan', rupiah(service)]);
        els.price.innerHTML = rows.map(([k, v]) => `<div class="flex justify-between gap-3 text-sm text-zinc-700"><span>${k}</span><span>${v}</span></div>`).join('')
            + `<div class="mt-3 flex justify-between gap-3 border-t border-zinc-200 pt-3 font-semibold"><span>Total</span><span>${rupiah(total)}</span></div>`;

        if (ready) {
            const end = endDate();
            let text = fmtLong(parse(state.date));
            if (isHour) { const [h, m] = state.start.split(':').map(Number); const eh = h + state.duration; text += `, ${state.start} - ${String(eh).padStart(2, '0')}:${String(m).padStart(2, '0')}`; }
            else if (end) text += ` sampai ${fmtLong(end)}`;
            els.summary.textContent = text;
        } else {
            els.summary.textContent = isHour ? 'Pilih tanggal dan jam mulai' : 'Pilih tanggal mulai';
        }
        els.submit.disabled = !ready;
        document.querySelectorAll('[data-sticky-total]').forEach((n) => { n.textContent = rupiah(total); });
    }

    els.cal.addEventListener('click', (e) => {
        const b = e.target.closest('[data-day]');
        if (!b || b.disabled) return;
        state.date = b.dataset.day; state.start = '';
        renderCalendar(); renderSummary();
        if (isHour) load();
    });
    els.slots?.addEventListener('click', (e) => {
        const b = e.target.closest('[data-time]');
        if (!b || b.disabled) return;
        state.start = b.dataset.time; renderSlots(); renderSummary();
    });
    els.prev.addEventListener('click', () => { state.month = new Date(state.month.getFullYear(), state.month.getMonth() - 1, 1); renderCalendar(); });
    els.next.addEventListener('click', () => { state.month = new Date(state.month.getFullYear(), state.month.getMonth() + 1, 1); renderCalendar(); });
    const changeDuration = (delta) => {
        state.duration = Math.min(cfg.maxDuration, Math.max(cfg.minDuration, state.duration + delta));
        renderSummary(); renderCalendar();
        if (isHour && state.date) load();
    };
    els.minus.addEventListener('click', () => changeDuration(-1));
    els.plus.addEventListener('click', () => changeDuration(1));

    if (state.date) state.month = new Date(parse(state.date).getFullYear(), parse(state.date).getMonth(), 1);
    renderCalendar(); renderSummary();
    load();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-booking-widget]').forEach(init);
});
