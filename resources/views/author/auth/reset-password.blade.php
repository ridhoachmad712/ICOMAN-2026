<x-author-layout :title="__('author.reset_title')">
    <div class="max-w-md mx-auto">
        <div class="card p-8">
            <h1 class="text-2xl font-bold text-[var(--brand-2)]">{{ __('author.reset_title') }}</h1>

            <form method="POST" action="{{ route('author.password.update') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.email') }}</label>
                    <input type="email" name="email" value="{{ old('email', $email) }}" required
                           class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.new_password') }}</label>
                    <input type="password" name="password" required
                           class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.password_confirm') }}</label>
                    <input type="password" name="password_confirmation" required
                           class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                </div>
                <button type="submit" class="btn btn-primary w-full">{{ __('author.reset_password') }}</button>
            </form>
        </div>
    </div>
</x-author-layout>
