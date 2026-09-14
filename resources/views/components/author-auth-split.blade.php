{{--
    Kerangka halaman login portal author: rel identitas di kiri, formulir di
    kanan dengan ruang kosong yang lapang.
--}}
<x-filament-panels::layout.base :livewire="$livewire">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)] xl:grid-cols-[minmax(0,30rem)_minmax(0,1fr)]">
        <x-author-auth-story />

        <div class="flex min-h-screen flex-col bg-white">
            <header class="flex items-center justify-between px-6 py-5 text-xs text-slate-400 sm:px-10">
                <a href="{{ route('home') }}" class="hover:text-slate-700">← {{ __('author.back_home') }}</a>
                <span class="uppercase tracking-[0.14em]">{{ __('author.portal') }}</span>
            </header>

            <main class="flex flex-1 items-center justify-center px-6 py-10">
                <div class="author-auth-form w-full max-w-sm">
                    {{-- Rel kiri tidak tampil di ponsel, jadi identitas acara diulang ringkas di sini. --}}
                    <p class="mb-8 text-center text-xs font-semibold uppercase tracking-[0.16em] text-[var(--brand,#d9621c)] lg:hidden">
                        {{ currentEdition()?->name ?: siteSettings()->conference_name }}
                    </p>

                    <div class="mb-8 flex justify-center">
                        <x-filament::icon
                            icon="heroicon-o-identification"
                            class="h-14 w-14 text-[var(--brand-2,#18315e)]"
                        />
                    </div>

                    {{ $slot }}
                </div>
            </main>

            <footer class="flex items-center justify-between px-6 py-5 text-xs text-slate-400 sm:px-10">
                <span class="lg:invisible">© {{ date('Y') }} {{ currentEdition()?->name ?: siteSettings()->conference_name }}</span>
                @if(siteSettings()->contact_email)
                    <a href="mailto:{{ siteSettings()->contact_email }}" class="hover:text-slate-700">{{ __('author.need_help') }} {{ siteSettings()->contact_email }}</a>
                @endif
            </footer>
        </div>
    </div>
</x-filament-panels::layout.base>
