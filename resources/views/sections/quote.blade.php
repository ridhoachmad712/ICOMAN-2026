<section class="bg-white py-16">
    <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
        <blockquote class="font-display text-2xl leading-relaxed font-medium text-[var(--brand-2)] sm:text-3xl">
            {!! $section->content !!}
        </blockquote>
        @if(filled($section->subheading))
            <p class="mt-6 text-sm font-semibold tracking-wide text-slate-500">- {{ $section->subheading }}</p>
        @endif
    </div>
</section>
