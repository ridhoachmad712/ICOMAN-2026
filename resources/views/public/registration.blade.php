<x-layout :title="__('nav.registration')">
    <x-page-header :title="__('site.registration_fees')" />

    @php
        $isId = app()->getLocale() === 'id';
        $presenterFees = $fees->where('audience', 'presenter')->values();
        $participantFees = $fees->where('audience', 'participant')->values();
        $earlyBirdDeadline = $fees->pluck('early_bird_deadline')->filter()->sort()->first();
    @endphp

    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
        <div class="mx-auto max-w-3xl text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--brand)]">
                {{ $isId ? 'Pilih jenis keikutsertaan' : 'Choose your participation type' }}
            </p>
            <h2 class="mt-3 font-display text-3xl font-bold tracking-tight text-[var(--brand-2)] sm:text-4xl">
                {{ $isId ? 'Anda ingin mempresentasikan paper atau mengikuti seminar?' : 'Will you present a paper or attend the seminar?' }}
            </h2>
            <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                {{ $isId
                    ? 'Pilih satu jalur sesuai tujuan Anda. Presenter otomatis mendapatkan akses seminar, sehingga tidak perlu membuat registrasi peserta kedua.'
                    : 'Choose one path based on your goal. Presenters automatically receive seminar access, so a second attendee registration is unnecessary.' }}
            </p>
        </div>

        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            <article data-reveal class="rounded-2xl border border-[var(--brand)]/30 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--brand)]">{{ $isId ? 'Jalur paper' : 'Paper path' }}</p>
                        <h3 class="mt-2 font-display text-2xl font-bold text-[var(--brand-2)]">{{ $isId ? 'Presenter' : 'Presenter' }}</h3>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-[var(--brand)]/10 text-[var(--brand)]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5V6.75A3.375 3.375 0 0 0 11.25 3.375H8.625m0 0H5.25A1.125 1.125 0 0 0 4.125 4.5v15A1.125 1.125 0 0 0 5.25 20.625h13.5a1.125 1.125 0 0 0 1.125-1.125v-4.125a1.125 1.125 0 0 0-1.125-1.125H8.625V3.375Z"/></svg>
                    </span>
                </div>

                <p class="mt-4 text-sm leading-6 text-slate-600">
                    {{ $isId
                        ? 'Untuk akademisi, peneliti, dosen, dan mahasiswa yang akan mengirim extended abstract serta mempresentasikan hasil penelitian.'
                        : 'For scholars, researchers, lecturers, and students submitting an extended abstract and presenting their research.' }}
                </p>

                <ol class="mt-6 grid gap-3 sm:grid-cols-2">
                    @foreach([
                        $isId ? 'Buat akun presenter' : 'Create a presenter account',
                        $isId ? 'Tulis extended abstract' : 'Write the extended abstract',
                        $isId ? 'Verifikasi reviewer' : 'Reviewer verification',
                        'Accepted',
                    ] as $step)
                        <li class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white text-xs font-bold text-[var(--brand)] shadow-sm">{{ $loop->iteration }}</span>
                            <span>{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-6 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ $isId ? 'Akses seminar dan sertifikat presenter sudah termasuk.' : 'Seminar access and presenter certificate are included.' }}</span>
                </div>

                <a href="{{ route('author.register', ['role' => 'presenter']) }}" class="btn btn-primary mt-7 w-full justify-center py-3 text-center">
                    {{ $isId ? 'Daftar sebagai Presenter' : 'Register as Presenter' }} →
                </a>
            </article>

            <article data-reveal class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $isId ? 'Jalur seminar' : 'Seminar path' }}</p>
                        <h3 class="mt-2 font-display text-2xl font-bold text-[var(--brand-2)]">{{ $isId ? 'Peserta Seminar' : 'Seminar Attendee' }}</h3>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 9.09 9.09 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                    </span>
                </div>

                <p class="mt-4 text-sm leading-6 text-slate-600">
                    {{ $isId
                        ? 'Untuk peserta umum, praktisi, pembuat kebijakan, dan mahasiswa yang ingin mengikuti keynote serta diskusi tanpa mengirim paper.'
                        : 'For general attendees, practitioners, policy makers, and students joining keynotes and discussions without submitting a paper.' }}
                </p>

                <ol class="mt-6 space-y-3">
                    @foreach([
                        $isId ? 'Buat akun peserta' : 'Create an attendee account',
                        $isId ? 'Pilih kategori registrasi' : 'Choose a registration category',
                        $isId ? 'Selesaikan pembayaran' : 'Complete payment',
                    ] as $step)
                        <li class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white text-xs font-bold text-slate-600 shadow-sm">{{ $loop->iteration }}</span>
                            <span>{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-6 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
                    <svg class="h-5 w-5 shrink-0 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    <span>{{ $isId ? 'Tidak perlu menyiapkan atau mengirim naskah.' : 'No manuscript preparation or submission is required.' }}</span>
                </div>

                <a href="{{ route('author.register', ['role' => 'non_presenter']) }}" class="btn btn-ghost mt-7 w-full justify-center bg-slate-900 py-3 text-center text-white hover:bg-slate-800">
                    {{ $isId ? 'Daftar sebagai Peserta' : 'Register as Attendee' }} →
                </a>
            </article>
        </div>

        <section class="mt-16 border-t border-slate-200 pt-12">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--brand)]">{{ $isId ? 'Biaya registrasi' : 'Registration fees' }}</p>
                    <h2 class="mt-2 font-display text-2xl font-bold text-[var(--brand-2)] sm:text-3xl">{{ $isId ? 'Pilih kategori yang sesuai' : 'Choose the applicable category' }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $isId ? 'Harga ditampilkan per peserta.' : 'Prices are shown per participant.' }}</p>
                </div>
                @if($earlyBirdDeadline)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <span class="font-semibold">Early bird</span>
                        <span class="ml-1">· {{ $isId ? 'hingga' : 'until' }} {{ $earlyBirdDeadline->format('d M Y') }}</span>
                    </div>
                @endif
            </div>

            @if($fees->isNotEmpty())
                <div class="mt-8 grid gap-6 lg:grid-cols-2">
                    @foreach([
                        ['label' => $isId ? 'Presenter' : 'Presenter', 'fees' => $presenterFees, 'role' => 'presenter'],
                        ['label' => $isId ? 'Peserta Seminar' : 'Seminar Attendee', 'fees' => $participantFees, 'role' => 'non_presenter'],
                    ] as $group)
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <div class="border-b border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                                <h3 class="font-display text-lg font-bold text-[var(--brand-2)]">{{ $group['label'] }}</h3>
                            </div>
                            <div class="divide-y divide-slate-100">
                                @forelse($group['fees'] as $fee)
                                    <div class="px-5 py-5 sm:px-6">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <h4 class="font-semibold text-slate-900">{{ $fee->category }}</h4>
                                                @if($fee->notes)<p class="mt-1 max-w-md text-xs leading-5 text-slate-500">{{ $fee->notes }}</p>@endif
                                            </div>
                                            <div class="shrink-0 text-left sm:text-right">
                                                @if($fee->price_early_bird)
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ __('site.early_bird') }}</p>
                                                    <p class="mt-0.5 font-display text-xl font-bold text-slate-950">{{ $fee->currency }} {{ number_format((float) $fee->price_early_bird, 0, ',', '.') }}</p>
                                                    <p class="mt-1 text-xs text-slate-500">{{ __('site.regular') }}: {{ $fee->currency }} {{ number_format((float) $fee->price_regular, 0, ',', '.') }}</p>
                                                @else
                                                    <p class="font-display text-xl font-bold text-slate-950">{{ $fee->currency }} {{ number_format((float) $fee->price_regular, 0, ',', '.') }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="px-5 py-6 text-sm text-slate-500">{{ $isId ? 'Kategori biaya belum tersedia.' : 'Fee categories are not available yet.' }}</p>
                                @endforelse
                            </div>
                            <div class="border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                                <a href="{{ route('author.register', ['role' => $group['role']]) }}" class="inline-flex items-center text-sm font-semibold text-[var(--brand)] hover:underline">
                                    {{ $group['role'] === 'presenter'
                                        ? ($isId ? 'Daftar sebagai Presenter' : 'Register as Presenter')
                                        : ($isId ? 'Daftar sebagai Peserta' : 'Register as Attendee') }} →
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-8"><x-empty-state /></div>
            @endif
        </section>

        <div class="mt-10 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-5 text-sm text-slate-600 sm:flex sm:items-center sm:justify-between sm:gap-6 sm:px-6">
            <p>{{ $isId ? 'Masih ragu memilih jalur atau kategori biaya?' : 'Still unsure which path or fee category applies?' }}</p>
            <a href="{{ route('contact') }}" class="mt-3 inline-flex font-semibold text-[var(--brand)] hover:underline sm:mt-0">{{ $isId ? 'Hubungi panitia' : 'Contact the committee' }} →</a>
        </div>
    </section>
</x-layout>
