<x-author-layout :title="__('author.reset_title')">
    <div class="max-w-md mx-auto">
        <div class="card p-6 sm:p-8 shadow-sm">
            {{-- Header Badge & Title --}}
            <div class="text-center pb-5 border-b border-slate-100 mb-6">
                <span class="inline-block text-[11px] uppercase tracking-widest font-bold text-[var(--brand)] bg-[var(--brand)]/10 px-3 py-1 rounded-full">
                    {{ siteSettings()->conference_name ?: 'ICOMAN 2026' }}
                </span>
                <h1 class="text-2xl font-bold font-display text-[var(--brand-2)] mt-3">
                    {{ __('author.reset_title') }}
                </h1>
            </div>

            <form method="POST" action="{{ route('author.password.update') }}" class="space-y-4" x-data="{ showPass: false, showPassConf: false }">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.email') }} *</label>
                    <div class="relative rounded-lg shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <input type="email" name="email" value="{{ old('email', $email) }}" required
                               class="w-full rounded-lg border-slate-300 pl-10 focus:border-[var(--brand)] focus:ring-[var(--brand)] text-sm"
                               placeholder="name@example.com">
                    </div>
                    @error('email')<p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.new_password') }} *</label>
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
                    @error('password')<p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
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

                <div class="pt-2">
                    <button type="submit" class="btn btn-primary w-full shadow-sm">{{ __('author.reset_password') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-author-layout>
