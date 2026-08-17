<x-layout :title="__('nav.speakers')">
    <x-page-header :title="__('site.keynote_speakers')" />

    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14">
        @if($speakers->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($speakers as $speaker)
                    <x-card-speaker :speaker="$speaker" />
                @endforeach
            </div>
        @else
            <x-empty-state />
        @endif
    </section>
</x-layout>
