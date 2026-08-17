// Alpine.js disediakan oleh Livewire (sudah dibundel) — jangan import Alpine terpisah
// agar tidak terjadi "multiple instances of Alpine".

// Scroll reveal: elemen dengan [data-reveal] akan fade-up saat masuk viewport.
function initReveal() {
    const els = [...document.querySelectorAll('[data-reveal]:not(.is-visible)')];
    if (! els.length) return;

    const revealAll = () => els.forEach((el) => el.classList.add('is-visible'));

    if (! ('IntersectionObserver' in window)) {
        revealAll();
        return;
    }

    const io = new IntersectionObserver((entries, obs) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                obs.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });

    els.forEach((el) => io.observe(el));

    // Fail-safe: apa pun yang terjadi (IO tak fire / tab background / prerender),
    // konten TIDAK boleh tetap tersembunyi. Ungkap semua setelah jeda singkat.
    setTimeout(revealAll, 1200);
}

// Vite memuat script sebagai module (deferred) → DOM biasanya sudah siap saat ini
// dijalankan, sehingga DOMContentLoaded bisa sudah lewat. Jalankan langsung + fallback.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReveal);
} else {
    initReveal();
}
document.addEventListener('livewire:navigated', initReveal);
