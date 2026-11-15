// Section SDGs: reveal saat gulir, hitung naik angka, dan buka/tutup detail kartu (ketuk atau keyboard).
import '../css/sdgs.css';

function init() {
    const root = document.querySelector('[data-sdgs]');
    if (!root) return;

    // Detail kartu: ketuk (layar sentuh) atau Enter/Spasi; Esc menutup. Hover dan fokus ditangani CSS.
    const tiles = [...root.querySelectorAll('[data-tile]')];
    tiles.forEach((tile) => {
        tile.addEventListener('click', (e) => {
            if (e.target.closest('a')) return;
            const open = !tile.classList.contains('is-open');
            tiles.forEach((t) => t.classList.remove('is-open'));
            tile.classList.toggle('is-open', open);
        });
        tile.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); tile.click(); }
            if (e.key === 'Escape') tile.classList.remove('is-open');
        });
    });

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce || !('IntersectionObserver' in window)) return; // konten tetap tampil statis

    root.classList.add('is-ready');
    root.querySelectorAll('.sdg__strip li').forEach((li, i) => li.style.setProperty('--i', i));

    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (!e.isIntersecting) return;
            e.target.classList.add('is-in');
            e.target.querySelectorAll('[data-count]').forEach(countUp);
            if (e.target.matches('[data-count]')) countUp(e.target);
            io.unobserve(e.target);
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -6% 0px' });
    root.querySelectorAll('[data-reveal], .sdg__strip').forEach((n) => io.observe(n));

    // Nilai akhir sudah tertulis di HTML; animasi hanya efek.
    function countUp(node) {
        if (node.dataset.done) return;
        node.dataset.done = '1';
        const end = parseInt(node.dataset.count, 10);
        if (!Number.isFinite(end) || end <= 0) return;
        const t0 = performance.now();
        const tick = (t) => {
            const p = Math.min(1, (t - t0) / 1200);
            node.textContent = Math.round(end * (1 - Math.pow(1 - p, 3))).toLocaleString('id-ID');
            if (p < 1) requestAnimationFrame(tick);
        };
        node.textContent = '0';
        requestAnimationFrame(tick);
    }
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
