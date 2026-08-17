<x-author-layout :title="__('author.login')">
    <div class="max-w-md mx-auto">
        <div class="card p-8">
            <h1 class="text-2xl font-bold text-[var(--brand-2)]">{{ __('author.login_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('author.login_subtitle') }}</p>

            @if(session('status'))
                <p class="mt-4 text-sm text-emerald-600">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('author.login') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.password') }}</label>
                    <input type="password" name="password" required
                           class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-slate-600">
                        <input type="checkbox" name="remember" class="rounded border-slate-300"> {{ __('author.remember_me') }}
                    </label>
                    <a href="{{ route('author.password.request') }}" class="text-[var(--brand)] hover:underline">{{ __('author.forgot_password') }}</a>
                </div>
                <button type="submit" class="btn btn-primary w-full">{{ __('author.login') }}</button>
            </form>

            <p class="mt-6 text-sm text-center text-slate-500">
                {{ __('author.no_account') }}
                <a href="{{ route('author.register') }}" class="text-[var(--brand)] font-medium hover:underline">{{ __('author.register') }}</a>
            </p>
        </div>
    </div>
</x-author-layout>
