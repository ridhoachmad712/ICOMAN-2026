{{--
    Kerangka split-screen untuk halaman login portal author.

    Dipasang sebagai layout Livewire pada halaman login Filament, sehingga
    halaman login memakai tampilan yang sama dengan halaman daftar — sebelumnya
    keduanya terlihat seperti dua produk berbeda.
--}}
<x-filament-panels::layout.base :livewire="$livewire">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,28rem)] xl:grid-cols-[minmax(0,1fr)_minmax(0,32rem)]">
        <x-author-auth-story />

        <div class="flex min-h-screen flex-col bg-white dark:bg-gray-950">
            <header class="flex items-center justify-between border-b border-gray-200 px-6 py-4 text-xs text-gray-500 dark:border-white/10">
                <a href="{{ route('home') }}" class="hover:text-gray-950 dark:hover:text-white">← {{ __('author.back_home') }}</a>
                <span class="uppercase tracking-[0.14em]">{{ __('author.portal') }}</span>
            </header>

            <main class="flex flex-1 items-center justify-center px-6 py-12">
                <div class="w-full max-w-sm">
                    {{-- Panel kiri tidak tampil di ponsel, jadi identitas acara diulang ringkas di sini. --}}
                    <p class="mb-6 text-center text-xs font-semibold uppercase tracking-[0.16em] text-[var(--brand,#d9621c)] lg:hidden">
                        {{ currentEdition()?->name ?: siteSettings()->conference_name }}
                    </p>

                    {{ $slot }}
                </div>
            </main>

            <footer class="flex items-center justify-between border-t border-gray-200 px-6 py-4 text-xs text-gray-500 dark:border-white/10">
                <span>© {{ date('Y') }} {{ currentEdition()?->name ?: siteSettings()->conference_name }}</span>
                @if(siteSettings()->contact_email)
                    <a href="mailto:{{ siteSettings()->contact_email }}" class="hover:text-gray-950 dark:hover:text-white">{{ __('author.need_help') }}</a>
                @endif
            </footer>
        </div>
    </div>
</x-filament-panels::layout.base>
