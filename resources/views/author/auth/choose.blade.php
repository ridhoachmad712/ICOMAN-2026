<x-author-layout :title="app()->getLocale() === 'id' ? 'Pilih Jenis Registrasi' : 'Choose Registration Type'">
    @php
        $isId = app()->getLocale() === 'id';
        $groups = [
            'presenter' => [
                'icon' => 'document',
                'title' => 'Presenter',
                'sub' => $isId ? 'Pemakalah — kirim abstrak & presentasi' : 'Author — submit an abstract & present',
            ],
            'non_presenter' => [
                'icon' => 'users',
                'title' => $isId ? 'Peserta Seminar' : 'Seminar Attendee',
                'sub' => $isId ? 'Ikuti seminar tanpa mengirim paper' : 'Join the seminar without submitting a paper',
            ],
        ];
        $cats = [
            'student_s1' => $isId ? 'Mahasiswa S1' : 'Undergraduate (S1)',
            'general' => $isId ? 'Dosen / Umum' : 'Lecturer / General',
        ];
    @endphp

    <div class="mx-auto max-w-2xl">
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
                    {{ $isId ? 'Pilih peran dan kategori Anda untuk melanjutkan ke pendaftaran akun.' : 'Select your role and category to continue to account registration.' }}
                </p>
            </div>

            <div class="space-y-6">
                @foreach($groups as $role => $group)
                    <div>
                        <div class="mb-3 flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[var(--brand)]/10 text-[var(--brand)]">
                                <x-ui-icon :name="$group['icon']" class="h-5 w-5" />
                            </span>
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">{{ $group['title'] }}</h2>
                                <p class="text-xs text-slate-500">{{ $group['sub'] }}</p>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach($cats as $catKey => $catLabel)
                                <a href="{{ route('author.register.start', ['role' => $role, 'category' => $catKey]) }}"
                                   class="group flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 transition hover:border-[var(--brand)] hover:bg-[var(--brand)]/5">
                                    <span class="text-sm font-semibold text-slate-800 group-hover:text-[var(--brand-2)]">{{ $catLabel }}</span>
                                    <x-ui-icon name="arrow-right" class="h-4 w-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-[var(--brand)]" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- S1 note --}}
            <div class="mt-6 flex items-start gap-2.5 rounded-lg bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-500">
                <x-ui-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                <span>{{ $isId ? 'Opsi Mahasiswa hanya berlaku untuk program S1. Mahasiswa S2/S3 silakan memilih Dosen / Umum.' : 'The student option applies to undergraduate (S1) programs only. Master’s (S2) and doctoral (S3) students should choose Lecturer / General.' }}</span>
            </div>

            {{-- Login switch --}}
            <p class="mt-6 border-t border-slate-100 pt-5 text-center text-sm text-slate-500">
                {{ __('author.have_account') }}
                <a href="{{ route('filament.author.auth.login') }}" class="ml-1 font-semibold text-[var(--brand)] hover:underline">{{ __('author.login') }} →</a>
            </p>
        </div>
    </div>
</x-author-layout>
