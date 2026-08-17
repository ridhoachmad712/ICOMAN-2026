<x-layout title="404">
    <section class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-24 text-center">
        <p class="text-7xl font-bold text-[var(--brand)]">404</p>
        <h1 class="mt-4 text-2xl font-bold text-[var(--brand-2)]">
            {{ app()->getLocale() === 'id' ? 'Halaman tidak ditemukan' : 'Page not found' }}
        </h1>
        <p class="mt-2 text-slate-500">
            {{ app()->getLocale() === 'id' ? 'Maaf, halaman yang Anda cari tidak tersedia.' : 'Sorry, the page you are looking for is not available.' }}
        </p>
        <a href="{{ route('home') }}" class="mt-8 inline-flex rounded-lg bg-[var(--brand)] px-6 py-3 font-semibold text-white hover:opacity-90">
            {{ __('nav.home') }}
        </a>
    </section>
</x-layout>
