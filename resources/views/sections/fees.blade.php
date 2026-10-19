@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

@php $records = $content->records($section); @endphp

    {{-- REGISTRATION TEASER (pricing) --}}
            <section class="section-tint section-y">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :section="$section" :title="$heading" :eyebrow="$eyebrow" :subtitle="$subheading" />
                @php
                    $audiences = [
                        'presenter' => __('site.home_presenter'),
                        'participant' => __('site.home_seminar_attendee'),
                    ];
                @endphp
                <div class="space-y-8">
                    @foreach($audiences as $aud => $audLabel)
                        @php $group = $records->where('audience', $aud)->sortBy('order'); @endphp
                        @if($group->isNotEmpty())
                            <div data-reveal>
                                <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold text-[var(--brand-2)]">
                                    <span class="h-5 w-1 rounded-full bg-[var(--brand)]"></span>{{ $audLabel }}
                                </h3>
                                <div class="grid items-stretch gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach($group as $fee)
                                        @php
                                            $mainPrice = $fee->currentPrice();
                                            $benefit = $fee->notes ? trim(strip_tags((string) $fee->notes)) : null;
                                        @endphp
                                        <div class="card flex flex-col p-6">
                                            <h4 class="text-base font-medium text-slate-600">{{ $fee->category }}</h4>
                                            <div class="mt-3 flex items-baseline gap-1.5">
                                                <span class="text-sm font-semibold text-slate-500">{{ $fee->currency }}</span>
                                                <span class="font-display text-3xl font-bold tracking-tight text-[var(--brand-2)]">{{ number_format((float) $mainPrice, 0, ',', '.') }}</span>
                                            </div>
                                            
                                            @if($benefit)
                                                <p class="mt-3 flex items-start gap-2 text-sm leading-relaxed text-slate-500">
                                                    <x-ui-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--brand-ink)]" />
                                                    {{ $benefit }}
                                                </p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-6 text-center">
                    <a href="{{ route('registration') }}" class="link-more">{{ __('site.view_fees') }}</a>
                </div>
            </div>
        </section>
