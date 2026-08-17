<x-layout :title="$page?->title ?? $fallbackTitle" :metaDescription="$page?->meta_description">
    <x-page-header :title="$page?->title ?? $fallbackTitle" />

    <section class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-14">
        @if($page && $page->content)
            <div class="prose prose-slate max-w-none">
                {!! $page->content !!}
            </div>
        @else
            <x-empty-state :message="__('site.no_content')" />
        @endif
    </section>
</x-layout>
