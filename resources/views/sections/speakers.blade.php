@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

    {{-- SPEAKERS --}}
    @php
        // Speaker "asli" = yang namanya bukan placeholder TBA. Kalau belum ada,
        // tampilkan state "To Be Announced" yang ringkas.
        $announcedSpeakers = $content->records($section)
            ->reject(fn ($sp) => str_contains(strtolower((string) $sp->name), 'tba'));
        $shouldScroll = $announcedSpeakers->count() > 1;
        // One copy must exceed the widest content area to avoid an empty gap.
        $repeat = $shouldScroll ? max(1, (int) ceil(5 / $announcedSpeakers->count())) : 1;
        $duration = max(28, $announcedSpeakers->count() * $repeat * 6);
    @endphp
    <section class="section-tint section-y">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-section-heading :section="$section" :title="$heading" :eyebrow="$eyebrow" :subtitle="$subheading" />

            @if($announcedSpeakers->isEmpty())
                {{-- Belum ada pembicara terkonfirmasi: satu baris ringkas, bukan
                     blok besar yang kosong. --}}
                <div data-reveal class="card mx-auto flex max-w-2xl items-center gap-4 p-5 text-left">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[var(--brand)]/10 text-[var(--brand-ink)]">
                        <x-ui-icon name="users" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="font-semibold text-[var(--brand-2)]">{{ __('site.home_to_be_announced') }}</p>
                        <p class="mt-0.5 text-sm text-slate-600">{{ __('site.speakers_pending_note') }}</p>
                    </div>
                </div>
            @else
                @if($shouldScroll)
                    <div class="mb-3 flex justify-end">
                        <button type="button" class="speaker-marquee-toggle text-sm font-medium text-[var(--brand-ink)] underline-offset-4 hover:underline focus-visible:underline"
                                data-speaker-marquee-toggle
                                data-pause-label="{{ app()->getLocale() === 'id' ? 'Jeda gerakan' : 'Pause motion' }}"
                                data-resume-label="{{ app()->getLocale() === 'id' ? 'Lanjutkan gerakan' : 'Resume motion' }}"
                                aria-pressed="false">
                            {{ app()->getLocale() === 'id' ? 'Jeda gerakan' : 'Pause motion' }}
                        </button>
                    </div>
                    <div class="speaker-marquee overflow-hidden" aria-label="{{ $heading ?: __('site.keynote_speakers') }}">
                        <div class="speaker-marquee-track flex w-max" style="animation-duration: {{ $duration }}s">
                            @foreach([1, 2] as $copy)
                                <div class="speaker-marquee-set flex w-max gap-6 pr-6" @if($copy === 2) aria-hidden="true" @endif>
                                    @for($repeatIndex = 0; $repeatIndex < $repeat; $repeatIndex++)
                                        @foreach($announcedSpeakers as $speaker)
                                            <div class="speaker-marquee-card w-64 shrink-0 sm:w-72" @if($repeatIndex > 0) aria-hidden="true" @endif>
                                                <x-card-speaker :speaker="$speaker" />
                                            </div>
                                        @endforeach
                                    @endfor
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="mx-auto max-w-72">
                        @foreach($announcedSpeakers as $speaker)
                            <x-card-speaker :speaker="$speaker" />
                        @endforeach
                    </div>
                @endif

                <div class="text-center mt-8">
                    <a href="{{ route('speakers') }}" class="link-more">{{ __('site.view_speakers') }}</a>
                </div>
            @endif
        </div>
    </section>
