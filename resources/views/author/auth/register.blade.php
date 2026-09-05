<x-author-layout :title="__('author.register_title')">
    @php
        $isId = app()->getLocale() === 'id';
        $input = 'w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20';
        $label = 'mb-1.5 block text-sm font-medium text-slate-700';
        // $role disediakan controller (sudah tervalidasi). Kategori dipilih via dropdown.
        $roleLabel = $role === 'presenter' ? 'Presenter' : ($isId ? 'Peserta Seminar' : 'Seminar Attendee');
    @endphp

    <div class="mx-auto max-w-lg">
        <div class="card p-6 sm:p-8">
            {{-- Header --}}
            <div class="mb-6 text-center">
                <span class="text-xs font-semibold uppercase tracking-widest text-[var(--brand)]">
                    {{ siteSettings()->conference_name ?: 'ICOMAN 2026' }}
                </span>
                <h1 class="mt-2 font-display text-2xl font-bold tracking-tight text-[var(--brand-2)]">
                    {{ __('author.register_title') }}
                </h1>
                <p class="mx-auto mt-1.5 max-w-sm text-sm leading-relaxed text-slate-500">
                    {{ $isId ? 'Lengkapi data akun untuk melanjutkan.' : 'Complete your account details to continue.' }}
                </p>
            </div>

            {{-- Chosen registration type --}}
            <div class="mb-6 flex items-center justify-between gap-3 rounded-xl border border-[var(--brand)]/30 bg-[var(--brand)]/5 px-4 py-3">
                <div class="min-w-0">
                    <span class="block text-[11px] font-semibold uppercase tracking-wider text-[var(--brand)]">{{ $isId ? 'Jenis registrasi' : 'Registration type' }}</span>
                    <span class="mt-0.5 block truncate text-sm font-semibold text-slate-900">{{ $roleLabel }}</span>
                </div>
                <a href="{{ route('author.register') }}" class="shrink-0 text-xs font-semibold text-[var(--brand)] hover:underline">{{ $isId ? 'Ubah' : 'Change' }}</a>
            </div>

            <form method="POST" action="{{ route('author.register') }}" class="space-y-5" x-data="{ showPass: false, showPassConf: false }">
                @csrf
                <input type="hidden" name="participation_type" value="{{ $role }}">

                {{-- Participant category --}}
                <div>
                    <label for="field-name" class="{{ $label }}">{{ $isId ? 'Kategori peserta' : 'Participant category' }} <span class="text-[var(--brand)]">*</span></label>
                    <select name="registrant_category" required class="{{ $input }}">
                        <option value="" disabled @selected(! old('registrant_category'))>{{ $isId ? 'Pilih kategori…' : 'Select a category…' }}</option>
                        @foreach(\App\Models\Author::CATEGORIES as $key => $lbl)
                            <option value="{{ $key }}" @selected(old('registrant_category') === $key)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs leading-relaxed text-slate-400">{{ $isId ? 'Mahasiswa hanya untuk program S1 (S2/S3 pilih Dosen/Umum). Peserta dari luar negeri pilih International.' : 'Student applies to undergraduate (S1) only (S2/S3 choose Lecturer/General). Overseas participants choose International.' }}</p>
                    @error('registrant_category')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Full name --}}
                <div>
                    <label class="{{ $label }}">{{ __('author.name') }} <span class="text-[var(--brand)]">*</span></label>
                    <input id="field-name" type="text" name="name" value="{{ old('name') }}" required class="{{ $input }}" placeholder="Dr. Jane Doe / John Doe, M.M.">
                    @error('name')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="field-email" class="{{ $label }}">{{ __('author.email') }} <span class="text-[var(--brand)]">*</span></label>
                    <input id="field-email" type="email" name="email" value="{{ old('email') }}" required class="{{ $input }}" placeholder="name@university.ac.id">
                    @error('email')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Affiliation & country --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="field-affiliation" class="{{ $label }}">{{ __('author.affiliation') }}</label>
                        <input id="field-affiliation" type="text" name="affiliation" value="{{ old('affiliation') }}" class="{{ $input }}" placeholder="{{ $isId ? 'Universitas / Instansi' : 'University / Institution' }}">
                    </div>
                    <div>
                        <label for="field-country" class="{{ $label }}">{{ __('author.country') }}</label>
                        <input id="field-country" type="text" name="country" value="{{ old('country') }}" class="{{ $input }}" placeholder="Indonesia / Malaysia">
                    </div>
                </div>

                {{-- Phone --}}
                <div>
                    <label for="field-phone" class="{{ $label }}">{{ __('author.phone') }}</label>
                    <input id="field-phone" type="text" name="phone" value="{{ old('phone') }}" class="{{ $input }}" placeholder="+62 812-3456-7890">
                </div>

                {{-- Passwords --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $label }}">{{ __('author.password') }} <span class="text-[var(--brand)]">*</span></label>
                        <div class="relative">
                            <input :type="showPass ? 'text' : 'password'" name="password" required class="{{ $input }} pr-10" placeholder="••••••••">
                            <button type="button" @click="showPass = !showPass" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none" aria-label="{{ $isId ? 'Tampilkan kata sandi' : 'Show password' }}">
                                <svg x-show="!showPass" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPass" x-cloak class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            </button>
                        </div>
                        @error('password')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('author.password_confirm') }} <span class="text-[var(--brand)]">*</span></label>
                        <div class="relative">
                            <input :type="showPassConf ? 'text' : 'password'" name="password_confirmation" required class="{{ $input }} pr-10" placeholder="••••••••">
                            <button type="button" @click="showPassConf = !showPassConf" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none" aria-label="{{ $isId ? 'Tampilkan kata sandi' : 'Show password' }}">
                                <svg x-show="!showPassConf" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPassConf" x-cloak class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full">{{ __('author.register') }}</button>
            </form>

            {{-- Login switch --}}
            <p class="mt-6 border-t border-slate-100 pt-5 text-center text-sm text-slate-500">
                {{ __('author.have_account') }}
                <a href="{{ route('filament.author.auth.login') }}" class="ml-1 font-semibold text-[var(--brand)] hover:underline">{{ __('author.login') }} →</a>
            </p>

            @if(siteSettings()->contact_email)
                <p class="mt-3 text-center text-xs text-slate-400">
                    {{ __('author.need_help') }}
                    <a href="mailto:{{ siteSettings()->contact_email }}" class="text-slate-500 underline hover:text-[var(--brand)]">{{ siteSettings()->contact_email }}</a>
                </p>
            @endif
        </div>
    </div>
</x-author-layout>
