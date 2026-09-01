<x-author-layout :title="__('author.forgot_title')">
    <div class="max-w-md mx-auto">
        <div class="card p-6 sm:p-8 shadow-sm">
            {{-- Header Badge & Title --}}
            <div class="text-center pb-5 border-b border-slate-100 mb-6">
                <span class="inline-block text-[11px] uppercase tracking-widest font-bold text-[var(--brand)] bg-[var(--brand)]/10 px-3 py-1 rounded-full">
                    {{ siteSettings()->conference_name ?: 'ICOMAN 2026' }}
                </span>
                <h1 class="text-2xl font-bold font-display text-[var(--brand-2)] mt-3">
                    {{ __('author.forgot_title') }}
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-500 max-w-sm mx-auto leading-relaxed">
                    {{ __('author.forgot_subtitle') }}
                </p>
            </div>

            <form method="POST" action="{{ route('author.password.email') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.email') }} *</label>
                    <div class="relative rounded-lg shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                               class="w-full rounded-lg border-slate-300 pl-10 focus:border-[var(--brand)] focus:ring-[var(--brand)] text-sm"
                               placeholder="name@example.com">
                    </div>
                    @error('email')<p class="mt-1.5 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>

                <div class="pt-2">
                    <button type="submit" class="btn btn-primary w-full shadow-sm">
                        {{ __('author.send_reset_link') }}
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-100 text-center">
                <a href="{{ route('filament.author.auth.login') }}" class="text-xs sm:text-sm text-[var(--brand)] font-medium hover:underline inline-flex items-center gap-1">
                    ← {{ __('author.login') }}
                </a>
            </div>
        </div>
    </div>
</x-author-layout>
