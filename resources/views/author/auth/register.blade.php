<x-author-layout :title="__('author.register')">
    <div class="max-w-lg mx-auto">
        <div class="card p-8">
            <h1 class="text-2xl font-bold text-[var(--brand-2)]">{{ __('author.register_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('author.register_subtitle') }}</p>

            <form method="POST" action="{{ route('author.register') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.name') }} *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.email') }} *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.affiliation') }}</label>
                        <input type="text" name="affiliation" value="{{ old('affiliation') }}"
                               class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.country') }}</label>
                        <input type="text" name="country" value="{{ old('country') }}"
                               class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.password') }} *</label>
                        <input type="password" name="password" required
                               class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.password_confirm') }} *</label>
                        <input type="password" name="password_confirmation" required
                               class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-full">{{ __('author.register') }}</button>
            </form>

            <p class="mt-6 text-sm text-center text-slate-500">
                {{ __('author.have_account') }}
                <a href="{{ route('author.login') }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('author.login') }}</a>
            </p>
        </div>
    </div>
</x-author-layout>
