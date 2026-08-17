<x-author-layout :title="__('author.forgot_title')">
    <div class="max-w-md mx-auto">
        <div class="card p-8">
            <h1 class="text-2xl font-bold text-[var(--brand-2)]">{{ __('author.forgot_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('author.forgot_subtitle') }}</p>

            @if(session('status'))
                <p class="mt-4 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('author.password.email') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('author.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full rounded-lg border-slate-300 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-primary w-full">{{ __('author.send_reset_link') }}</button>
            </form>

            <p class="mt-6 text-sm text-center">
                <a href="{{ route('author.login') }}" class="text-[var(--brand)] hover:underline">← {{ __('author.login') }}</a>
            </p>
        </div>
    </div>
</x-author-layout>
