<x-layout :title="__('nav.contact')">
    <x-page-header :title="__('site.faq_title')" />

    <section class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-14">
        @if($faqs->isNotEmpty())
            <div class="space-y-3">
                @foreach($faqs as $faq)
                    <div x-data="{ open: false }" class="card overflow-hidden">
                        <button @click="open = !open" class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left">
                            <span class="font-medium text-slate-800">{{ $faq->question }}</span>
                            <svg class="h-5 w-5 text-slate-400 transition-transform shrink-0" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition x-cloak class="px-5 pb-4 text-slate-600 whitespace-pre-line">{{ $faq->answer }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <x-empty-state />
        @endif
    </section>
</x-layout>
