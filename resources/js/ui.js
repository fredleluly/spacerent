// Interaksi UI umum. Semua progresif: halaman tetap bisa dipakai tanpa JS.

const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

document.addEventListener('DOMContentLoaded', () => {
    // Pesan flash hilang sendiri (kecuali error).
    $$('[data-flash]').forEach((el) => {
        if (el.dataset.flash === 'error') return;
        setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 320); }, 5000);
    });
    $$('[data-dismiss]').forEach((b) => b.addEventListener('click', () => b.closest('[data-flash]')?.remove()));

    // Toggle menu: <button data-toggle="#id">
    $$('[data-toggle]').forEach((btn) => btn.addEventListener('click', () => {
        const target = document.querySelector(btn.dataset.toggle);
        if (!target) return;
        const open = target.hasAttribute('hidden');
        target.toggleAttribute('hidden', !open);
        btn.setAttribute('aria-expanded', String(open));
    }));

    // Dialog native: data-dialog-open="#id" / data-dialog-close
    $$('[data-dialog-open]').forEach((b) => b.addEventListener('click', () => document.querySelector(b.dataset.dialogOpen)?.showModal()));
    $$('[data-dialog-close]').forEach((b) => b.addEventListener('click', () => b.closest('dialog')?.close()));
    $$('dialog').forEach((d) => d.addEventListener('click', (e) => { if (e.target === d) d.close(); }));

    // Cegah klik ganda pada form penting.
    $$('form[data-once]').forEach((form) => form.addEventListener('submit', (e) => {
        if (form.dataset.submitting) { e.preventDefault(); return; }
        form.dataset.submitting = '1';
        $$('button[type=submit]', form).forEach((b) => {
            b.disabled = true;
            if (b.dataset.loading) b.textContent = b.dataset.loading;
        });
    }));

    // Konfirmasi aksi berisiko tanpa dialog bawaan browser yang jelek: form[data-confirm]
    $$('form[data-confirm]').forEach((form) => form.addEventListener('submit', (e) => {
        if (!window.confirm(form.dataset.confirm)) { e.preventDefault(); form.dataset.submitting = ''; $$('button[type=submit]', form).forEach((b) => { b.disabled = false; }); }
    }));

    // Hitung mundur: <span data-countdown="ISO8601">. Muat ulang saat habis.
    $$('[data-countdown]').forEach((el) => {
        const end = new Date(el.dataset.countdown).getTime();
        const tick = () => {
            const left = Math.floor((end - Date.now()) / 1000);
            if (left <= 0) { el.textContent = '00:00'; setTimeout(() => window.location.reload(), 1200); return false; }
            const h = Math.floor(left / 3600);
            const m = String(Math.floor((left % 3600) / 60)).padStart(2, '0');
            const s = String(left % 60).padStart(2, '0');
            el.textContent = h > 0 ? `${h}:${m}:${s}` : `${m}:${s}`;
            return true;
        };
        if (tick()) { const id = setInterval(() => { if (!tick()) clearInterval(id); }, 1000); }
    });

    // Input bintang: <div class="star-input" data-stars data-name="rating" data-value="0">
    $$('[data-stars]').forEach((box) => {
        const name = box.dataset.name;
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = name; input.value = box.dataset.value || '';
        box.append(input);
        const buttons = [];
        for (let i = 1; i <= 5; i++) {
            const b = document.createElement('button');
            b.type = 'button'; b.setAttribute('aria-label', `${i} bintang`);
            b.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
            b.addEventListener('click', () => set(i));
            buttons.push(b); box.insertBefore(b, input);
        }
        const set = (v) => { input.value = v; buttons.forEach((b, idx) => b.classList.toggle('is-on', idx < v)); box.dispatchEvent(new CustomEvent('change', { detail: v })); };
        set(Number(input.value) || 0);
    });

    // Pratinjau foto unggahan: <input type=file data-preview="#box" multiple>
    $$('input[data-preview]').forEach((input) => input.addEventListener('change', () => {
        const box = document.querySelector(input.dataset.preview);
        if (!box) return;
        box.innerHTML = '';
        const files = [...input.files].slice(0, 4);
        if (input.files.length > 4) { input.value = ''; box.textContent = 'Maksimal 4 foto.'; return; }
        files.forEach((f) => {
            if (f.size > 3 * 1024 * 1024) { box.textContent = `${f.name} lebih dari 3 MB.`; input.value = ''; return; }
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f); img.alt = f.name;
            img.className = 'h-20 w-20 rounded-xl object-cover';
            box.append(img);
        });
    }));

    // Galeri: klik gambar [data-lightbox] membuka dialog besar.
    const dlg = document.querySelector('#lightbox');
    if (dlg) {
        const imgs = $$('[data-lightbox]');
        const big = dlg.querySelector('img');
        let idx = 0;
        const show = (i) => { idx = (i + imgs.length) % imgs.length; big.src = imgs[idx].dataset.lightbox; big.alt = imgs[idx].alt || ''; };
        imgs.forEach((el, i) => el.addEventListener('click', () => { show(i); dlg.showModal(); }));
        dlg.querySelector('[data-prev]')?.addEventListener('click', () => show(idx - 1));
        dlg.querySelector('[data-next]')?.addEventListener('click', () => show(idx + 1));
        dlg.addEventListener('keydown', (e) => { if (e.key === 'ArrowLeft') show(idx - 1); if (e.key === 'ArrowRight') show(idx + 1); });
    }

    // Salin teks: <button data-copy="teks">
    $$('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(b.dataset.copy); const t = b.textContent; b.textContent = 'Tersalin'; setTimeout(() => { b.textContent = t; }, 1500); } catch { /* abaikan */ }
    }));
});
