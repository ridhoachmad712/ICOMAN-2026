@php
    $documentSections = app(\App\Services\ExtendedAbstractDocument::class)->sections($submission, $embedImages ?? false);
@endphp

@once
    <style>
        .extended-abstract-document { color: #374151; font-size: .925rem; line-height: 1.75; }
        .extended-abstract-section + .extended-abstract-section { border-top: 1px solid #e5e7eb; margin-top: 1.5rem; padding-top: 1.5rem; }
        .extended-abstract-section > h2 { color: #111827; font-size: 1rem; font-weight: 700; letter-spacing: .025em; margin-bottom: .75rem; text-transform: uppercase; }
        .extended-abstract-content p { margin: 0 0 .85rem; }
        .extended-abstract-content img { border-radius: .5rem; display: block; height: auto; margin: 1rem auto; max-width: 100%; }
        .extended-abstract-content table { border-collapse: collapse; margin: 1rem 0; width: 100%; }
        .extended-abstract-content td, .extended-abstract-content th { border: 1px solid #d1d5db; padding: .5rem; }
        .extended-abstract-content blockquote { border-left: 3px solid #9ca3af; color: #6b7280; margin-left: 0; padding-left: 1rem; }
        .extended-abstract-content .equation { display: block; margin: 1rem 0; overflow-x: auto; text-align: center; }
        .extended-abstract-content .equation-inline { display: inline; margin: 0 .2rem; }
        .extended-abstract-content .equation code { background: #f3f4f6; border-radius: .3rem; color: #111827; padding: .25rem .5rem; }
    </style>
@endonce

<article class="extended-abstract-document">
    @foreach($documentSections as $section)
        @continue(blank($section['text']))
        <section class="extended-abstract-section">
            <h2>{{ $section['label'] }}</h2>
            <div class="extended-abstract-content prose max-w-none">
                {!! $section['html'] !!}
            </div>
        </section>
    @endforeach
</article>
