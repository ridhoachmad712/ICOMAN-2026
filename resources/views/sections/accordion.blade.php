@php
    $locale = app()->getLocale();
    $items = collect($section->setting('items', []));
@endphp

<section class="section-tint py-16">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        @if(filled($section->heading))
            <x-section-heading :section="$section" :title="$section->heading" :eyebrow="$section->eyebrow" :subtitle="$section->subheading" />
        @endif

        <div class="space-y-3">
            @foreach($items as $item)
                @php
                    $question = $item['question_'.$locale] ?? $item['question_id'] ?? $item['question_en'] ?? null;
                    $answer = $item['answer_'.$locale] ?? $item['answer_id'] ?? $item['answer_en'] ?? null;
                @endphp
                @continue(blank($question))

                <div x-data="{ open: false }" class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <button type="button" @click="open = !open" :aria-expanded="open"
                            class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left">
                        <span class="font-medium text-slate-800">{{ $question }}</span>
                        <svg class="h-5 w-5 shrink-0 text-slate-400 transition-transform" :class="open && 'rotate-180'"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-transition x-cloak class="px-5 pb-4 text-slate-600">{!! $answer !!}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
