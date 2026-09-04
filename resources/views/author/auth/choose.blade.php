<x-author-layout :title="app()->getLocale() === 'id' ? 'Pilih Jenis Registrasi' : 'Choose Registration Type'">
    @php
        $isId = app()->getLocale() === 'id';
        $roles = [
            'presenter' => [
                'icon' => 'document',
                'title' => 'Presenter',
                'sub' => $isId ? 'Kirim abstrak & presentasikan penelitian Anda' : 'Submit an abstract & present your research',
            ],
            'non_presenter' => [
                'icon' => 'users',
                'title' => $isId ? 'Peserta Seminar' : 'Seminar Attendee',
                'sub' => $isId ? 'Ikuti seminar tanpa mengirim paper' : 'Join the seminar without submitting a paper',
            ],
        ];
    @endphp

    <div class="mx-auto max-w-xl">
        <div class="card p-6 sm:p-8">
            {{-- Header --}}
            <div class="mb-7 text-center">
                <span class="text-xs font-semibold uppercase tracking-widest text-[var(--brand)]">
                    {{ siteSettings()->conference_name ?: 'ICOMAN 2026' }}
                </span>
                <h1 class="mt-2 font-display text-2xl font-bold tracking-tight text-[var(--brand-2)]">
                    {{ $isId ? 'Pilih Jenis Registrasi' : 'Choose Registration Type' }}
                </h1>
                <p class="mx-auto mt-1.5 max-w-md text-sm leading-relaxed text-slate-500">
                    {{ $isId ? 'Pilih peran Anda untuk melanjutkan. Kategori peserta dipilih pada langkah berikutnya.' : 'Choose your role to continue. Your participant category is selected in the next step.' }}
                </p>
            </div>

            <div class="grid gap-4">
                @foreach($roles as $role => $meta)
                    <a href="{{ route('author.register.terms', ['role' => $role]) }}"
                       class="group flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 transition hover:border-[var(--brand)] hover:bg-[var(--brand)]/5">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[var(--brand)]/10 text-[var(--brand)]">
                            <x-ui-icon :name="$meta['icon']" class="h-6 w-6" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-base font-bold text-slate-900 group-hover:text-[var(--brand-2)]">{{ $meta['title'] }}</span>
                            <span class="mt-0.5 block text-sm leading-relaxed text-slate-500">{{ $meta['sub'] }}</span>
                        </span>
                        <x-ui-icon name="arrow-right" class="h-5 w-5 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-[var(--brand)]" />
                    </a>
                @endforeach
            </div>

            {{-- Login switch --}}
            <p class="mt-7 border-t border-slate-100 pt-5 text-center text-sm text-slate-500">
                {{ __('author.have_account') }}
                <a href="{{ route('filament.author.auth.login') }}" class="ml-1 font-semibold text-[var(--brand)] hover:underline">{{ __('author.login') }} →</a>
            </p>
        </div>
    </div>
</x-author-layout>
