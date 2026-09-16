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
        $announcedSpeakers = $content->records($section)->reject(fn ($sp) => str_contains(strtolower((string) $sp->name), 'tba'));
    @endphp
    <section class="bg-white py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-section-heading :section="$section" :title="$heading" :eyebrow="$eyebrow" :subtitle="$subheading" />

            @if($announcedSpeakers->isEmpty())
                <div data-reveal class="mx-auto max-w-md text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--brand)]/10 text-[var(--brand-ink)]">
                        <x-ui-icon name="users" class="h-6 w-6" />
                    </div>
                    <p class="text-lg font-semibold text-[var(--brand-2)]">{{ __('site.home_to_be_announced') }}</p>
                </div>
            @else
                {{-- Semua pembicara setara dalam satu carousel (tanpa kartu
                     spotlight terpisah), 4 kartu per tampilan di desktop. --}}
                <x-speaker-carousel :speakers="$announcedSpeakers" />

                <div class="text-center mt-8">
                    <a href="{{ route('speakers') }}" class="link-more">{{ __('site.view_speakers') }}</a>
                </div>
            @endif
        </div>
    </section>
