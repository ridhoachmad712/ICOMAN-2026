@php
    $s = siteSettings();
    $edition = currentEdition();
    $content = app(\App\Services\SectionContent::class);
    $tierOrder = ['platinum' => 'Platinum', 'gold' => 'Gold', 'silver' => 'Silver', 'partner' => 'Partner', 'media_partner' => 'Media Partner'];
    $heading = $section->heading;
    $eyebrow = $section->eyebrow;
    $subheading = $section->subheading;
@endphp

@php $grouped = $content->sponsorsByTier($section); @endphp

    {{-- SPONSORS (grouped by tier) --}}
            <section class="bg-white py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading :title="__('site.our_sponsors')" />
                <div class="space-y-8">
                    @foreach($tierOrder as $tierKey => $tierLabel)
                        @if($grouped->has($tierKey))
                            <div>
                                <p class="text-center text-xs uppercase tracking-widest text-slate-400 mb-4">{{ $tierLabel }}</p>
                                <div class="flex flex-wrap items-center justify-center gap-8">
                                    @foreach($sponsors->get($tierKey) as $sponsor)
                                        @php $logo = $sponsor->getFirstMediaUrl('logo', 'thumb'); @endphp
                                        <div class="grayscale hover:grayscale-0 transition">
                                            @if($logo)
                                                <img src="{{ $logo }}" alt="{{ $sponsor->name }}" loading="lazy" class="h-14 w-auto object-contain">
                                            @else
                                                <span class="text-slate-400 font-medium">{{ $sponsor->name }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
