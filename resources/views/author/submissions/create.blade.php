<x-author-layout :title="__('author.submit_paper')">
    <div class="max-w-3xl mx-auto">
        <a href="{{ route('author.dashboard') }}" class="text-sm text-[var(--brand)] hover:underline">← {{ __('author.back_dashboard') }}</a>
        <h1 class="mt-2 text-2xl font-bold text-[var(--brand-2)]">{{ __('author.submit_paper') }}</h1>

        <div class="mt-6">
            @livewire('author.submit-paper')
        </div>
    </div>
</x-author-layout>
