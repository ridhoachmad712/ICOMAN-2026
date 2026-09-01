<x-author-layout :title="__('author.register_title')">
    <div class="max-w-lg mx-auto">
        <div class="card p-6 sm:p-8 shadow-sm">
            {{-- Header Badge & Conference Title --}}
            <div class="text-center pb-5 border-b border-slate-100 mb-6">
                <span class="inline-block text-[11px] uppercase tracking-widest font-bold text-[var(--brand)] bg-[var(--brand)]/10 px-3 py-1 rounded-full">
                    {{ siteSettings()->conference_name ?: 'ICOMAN 2026' }}
                </span>
                <h1 class="text-2xl font-bold font-display text-[var(--brand-2)] mt-3">
                    {{ __('author.register_title') }}
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-500 max-w-sm mx-auto leading-relaxed">
                    {{ __('author.register_subtitle') }}
                </p>
            </div>

            <form method="POST" action="{{ route('author.register') }}" class="space-y-4" x-data="{ showPass: false, showPassConf: false }">
                @csrf

                {{-- Participation Type Selection --}}
                <div x-data="{ role: '{{ old('participation_type', request('role', 'presenter')) }}' }">
                    <label class="block text-sm font-semibold text-slate-800 mb-2">
                        {{ app()->getLocale() === 'id' ? 'Pilih Jenis Pendaftaran' : 'Select Registration Type' }} *
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="cursor-pointer rounded-xl border-2 p-3.5 transition flex flex-col justify-between"
                               :class="role === 'presenter' ? 'border-[var(--brand)] bg-[var(--brand)]/5 ring-1 ring-[var(--brand)] shadow-xs' : 'border-slate-200 bg-white hover:border-slate-300'">
                            <input type="radio" name="participation_type" value="presenter" x-model="role" class="sr-only" required>
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-slate-900 text-sm">Presenter</span>
                                    <span class="text-[10px] uppercase tracking-wider font-bold text-[var(--brand)] bg-[var(--brand)]/10 px-2 py-0.5 rounded">Conference</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                                    {{ app()->getLocale() === 'id' ? 'Pemakalah (Input abstrak dan maks. 5 keyword di portal)' : 'Author (Enter an abstract and up to 5 keywords in the portal)' }}
                                </p>
                            </div>
                            <div class="mt-2.5 pt-2 border-t border-slate-100/80 text-[11px] font-medium text-emerald-700 flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>{{ app()->getLocale() === 'id' ? 'Wajib Input Abstrak' : 'Abstract Entry Required' }}</span>
                            </div>
                        </label>

                        <label class="cursor-pointer rounded-xl border-2 p-3.5 transition flex flex-col justify-between"
                               :class="role === 'non_presenter' ? 'border-[var(--brand)] bg-[var(--brand)]/5 ring-1 ring-[var(--brand)] shadow-xs' : 'border-slate-200 bg-white hover:border-slate-300'">
                            <input type="radio" name="participation_type" value="non_presenter" x-model="role" class="sr-only" required>
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-slate-900 text-sm">Non-Presenter</span>
                                    <span class="text-[10px] uppercase tracking-wider font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">Seminar</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                                    {{ app()->getLocale() === 'id' ? 'Peserta seminar (Isi data tanpa perlu mengirim paper)' : 'Attendee (Register without submitting a paper)' }}
                                </p>
                            </div>
                            <div class="mt-2.5 pt-2 border-t border-slate-100/80 text-[11px] font-medium text-blue-700 flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 shrink-0 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <span>{{ app()->getLocale() === 'id' ? 'Akses Seminar Zoom' : 'Seminar Zoom Access' }}</span>
                            </div>
                        </label>
                    </div>
                    @error('participation_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Full Name --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.name') }} *</label>
                    <div class="relative rounded-lg shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full rounded-lg border-slate-300 pl-10 focus:border-[var(--brand)] focus:ring-[var(--brand)] text-sm"
                               placeholder="Dr. Jane Doe / John Doe, M.M.">
                    </div>
                    @error('name')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>

                {{-- Email Address --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.email') }} *</label>
                    <div class="relative rounded-lg shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="w-full rounded-lg border-slate-300 pl-10 focus:border-[var(--brand)] focus:ring-[var(--brand)] text-sm"
                               placeholder="name@university.ac.id">
                    </div>
                    @error('email')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>

                {{-- Affiliation & Country --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.affiliation') }}</label>
                        <div class="relative rounded-lg shadow-sm">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <input type="text" name="affiliation" value="{{ old('affiliation') }}"
                                   class="w-full rounded-lg border-slate-300 pl-10 focus:border-[var(--brand)] focus:ring-[var(--brand)] text-sm"
                                   placeholder="Universitas / Instansi">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.country') }}</label>
                        <div class="relative rounded-lg shadow-sm">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <input type="text" name="country" value="{{ old('country') }}"
                                   class="w-full rounded-lg border-slate-300 pl-10 focus:border-[var(--brand)] focus:ring-[var(--brand)] text-sm"
                                   placeholder="Indonesia / Malaysia">
                        </div>
                    </div>
                </div>

                {{-- Phone / WhatsApp --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.phone') }}</label>
                    <div class="relative rounded-lg shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </div>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                               class="w-full rounded-lg border-slate-300 pl-10 focus:border-[var(--brand)] focus:ring-[var(--brand)] text-sm"
                               placeholder="+62 812-3456-7890">
                    </div>
                </div>

                {{-- Password & Confirm Password with Toggles --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.password') }} *</label>
                        <div class="relative rounded-lg shadow-sm">
                            <input :type="showPass ? 'text' : 'password'" name="password" required
                                   class="w-full rounded-lg border-slate-300 pr-10 focus:border-[var(--brand)] focus:ring-[var(--brand)] text-sm"
                                   placeholder="••••••••">
                            <button type="button" @click="showPass = !showPass"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none">
                                <svg x-show="!showPass" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPass" x-cloak class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            </button>
                        </div>
                        @error('password')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.password_confirm') }} *</label>
                        <div class="relative rounded-lg shadow-sm">
                            <input :type="showPassConf ? 'text' : 'password'" name="password_confirmation" required
                                   class="w-full rounded-lg border-slate-300 pr-10 focus:border-[var(--brand)] focus:ring-[var(--brand)] text-sm"
                                   placeholder="••••••••">
                            <button type="button" @click="showPassConf = !showPassConf"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none">
                                <svg x-show="!showPassConf" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPassConf" x-cloak class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="pt-2">
                    <button type="submit" class="btn btn-primary w-full shadow-sm">
                        {{ __('author.register') }}
                    </button>
                </div>
            </form>

            {{-- Login Switch --}}
            <div class="mt-6 pt-5 border-t border-slate-100 text-center">
                <p class="text-xs sm:text-sm text-slate-500">
                    {{ __('author.have_account') }}
                    <a href="{{ route('filament.author.auth.login') }}" class="text-[var(--brand)] font-semibold hover:underline ml-1">
                        {{ __('author.login') }} →
                    </a>
                </p>
            </div>

            {{-- Secretariat Support Info --}}
            @if(siteSettings()->contact_email)
                <div class="mt-4 text-center">
                    <p class="text-[11px] text-slate-400">
                        {{ __('author.need_help') }}
                        <a href="mailto:{{ siteSettings()->contact_email }}" class="text-slate-500 hover:text-[var(--brand)] underline">
                            {{ siteSettings()->contact_email }}
                        </a>
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-author-layout>
