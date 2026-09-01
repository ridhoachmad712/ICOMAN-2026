<x-layout :title="__('nav.speakers')">
    @php
        $confirmedSpeakers = $speakers->filter(fn ($speaker) => ! \Illuminate\Support\Str::contains(
            \Illuminate\Support\Str::lower((string) $speaker->name), ['tba', 'to be announced']
        ));
    @endphp
    <x-page-header :title="__('site.keynote_speakers')" />

    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14">
        @if($confirmedSpeakers->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($confirmedSpeakers as $speaker)
                    <x-card-speaker :speaker="$speaker" />
                @endforeach
            </div>
        @else
            <div class="mx-auto max-w-xl border-y border-slate-200 py-10 text-center">
                <h2 class="text-xl font-semibold text-slate-900">{{ app()->getLocale() === 'id' ? 'Pembicara akan diumumkan' : 'Speakers will be announced' }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ app()->getLocale() === 'id' ? 'Profil hanya akan ditampilkan setelah dikonfirmasi oleh panitia.' : 'Profiles will appear here after confirmation by the committee.' }}</p>
            </div>
        @endif
    </section>
</x-layout>
