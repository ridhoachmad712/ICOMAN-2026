<x-layout :title="__('nav.cfp')">
    <x-page-header :title="__('site.call_for_papers')" />

    <section class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-14 space-y-14">
        {{-- CFP intro (dari CMS page opsional) --}}
        @if($page && $page->content)
            <div class="prose prose-slate max-w-none">{!! str_ireplace(['Full Paper', 'full paper'], ['Abstract', 'abstract'], (string) $page->content) !!}</div>
        @endif

        {{-- Topics --}}
        <div>
            <h2 class="font-display text-2xl font-bold text-[var(--brand-2)] mb-6">{{ __('site.topics') }}</h2>
            @if($topics->isNotEmpty())
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($topics as $topic)
                        <div class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                            <span class="mt-1 h-2 w-2 rounded-full bg-[var(--brand)] shrink-0"></span>
                            <span class="text-slate-700">{{ $topic->title }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <x-empty-state />
            @endif
        </div>

        {{-- Templates --}}
        @if($templates->isNotEmpty())
            <div>
                <h2 class="font-display text-2xl font-bold text-[var(--brand-2)] mb-6">{{ __('site.templates') }}</h2>
                <ul class="divide-y divide-slate-200 card overflow-hidden">
                    @foreach($templates as $t)
                        @php $file = $t->getFirstMediaUrl('file'); @endphp
                        <li class="flex items-center justify-between gap-4 px-5 py-4">
                            <span class="font-medium text-slate-700">{{ $t->title }}</span>
                            @if($file)
                                <a href="{{ $file }}" target="_blank" rel="noopener"
                                   class="text-sm font-semibold text-[var(--brand)] hover:underline whitespace-nowrap">{{ __('site.download') }} ↓</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="border-y border-slate-200 bg-slate-50 p-8 text-center">
            <p class="text-slate-600 mb-4">{{ app()->getLocale() === 'id' ? 'Siap menulis abstract Anda?' : 'Ready to write your abstract?' }}</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="{{ route('author.register', ['role' => 'presenter']) }}" class="btn btn-primary">{{ app()->getLocale() === 'id' ? 'Mulai Abstract' : 'Start Abstract' }}</a>
                <a href="{{ route('registration') }}" class="btn btn-outline">{{ __('site.registration_fees') }}</a>
            </div>
        </div>
    </section>
</x-layout>
