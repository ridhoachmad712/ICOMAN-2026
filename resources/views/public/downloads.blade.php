<x-layout :title="__('nav.cfp')">
    <x-page-header :title="__('site.templates')" />

    <section class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-14">
        @if($downloads->isNotEmpty())
            <ul class="divide-y divide-slate-200 card overflow-hidden">
                @foreach($downloads as $d)
                    @php $file = $d->getFirstMediaUrl('file'); @endphp
                    <li class="flex items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p class="font-medium text-slate-800">{{ $d->title }}</p>
                            <span class="text-xs uppercase tracking-wide text-slate-400">{{ $d->category }}</span>
                        </div>
                        @if($file)
                            <a href="{{ $file }}" target="_blank" rel="noopener"
                               class="btn btn-primary text-sm whitespace-nowrap">{{ __('site.download') }} ↓</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <x-empty-state />
        @endif
    </section>
</x-layout>
