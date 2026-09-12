@php
    $content = app(\App\Services\SectionContent::class);
    $downloads = $content->records($section);
@endphp

    <section class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-14">
        <x-submission-guidelines />
        <h2 class="mb-5 mt-10 text-xl font-bold">{{ __('site.downloads_additional_committee_documents') }}</h2>
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
            <p class="text-sm text-slate-500">{{ __('site.downloads_additional_documents_will_appear_here') }}</p>
        @endif
    </section>
