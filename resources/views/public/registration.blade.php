<x-layout :title="__('nav.registration')">
    <x-page-header :title="__('site.registration_fees')" />

    <section class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-14">
        @if($fees->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($fees as $fee)
                    <div class="card card-hover p-6 flex flex-col">
                        <h3 class="text-lg font-bold text-[var(--brand-2)]">{{ $fee->category }}</h3>

                        <div class="mt-4 space-y-2">
                            @if($fee->price_early_bird)
                                <div class="flex items-baseline justify-between">
                                    <span class="text-xs uppercase tracking-wide text-emerald-600 font-semibold">{{ __('site.early_bird') }}</span>
                                    <span class="text-2xl font-bold text-slate-900">{{ $fee->currency }} {{ number_format((float) $fee->price_early_bird, 0, ',', '.') }}</span>
                                </div>
                            @endif
                            <div class="flex items-baseline justify-between">
                                <span class="text-xs uppercase tracking-wide text-slate-400 font-semibold">{{ __('site.regular') }}</span>
                                <span class="text-xl font-semibold text-slate-700">{{ $fee->currency }} {{ number_format((float) $fee->price_regular, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        @if($fee->notes)<p class="mt-4 text-sm text-slate-500">{{ $fee->notes }}</p>@endif
                    </div>
                @endforeach
            </div>

            <div class="mt-10 rounded-2xl section-tint p-8 text-center">
                <p class="text-slate-600 mb-4">{{ app()->getLocale() === 'id' ? 'Daftar sekarang melalui portal peserta.' : 'Register now through the participant portal.' }}</p>
                <a href="{{ route('author.registration.create') }}" class="btn btn-primary">{{ __('site.register_now') }}</a>
            </div>
        @else
            <x-empty-state />
        @endif
    </section>
</x-layout>
