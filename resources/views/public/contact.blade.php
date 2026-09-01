<x-layout :title="__('nav.contact')">
    <x-page-header :title="__('site.contact_us')" />

    <section class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-14 grid gap-10 md:grid-cols-2">
        {{-- Info --}}
        <div class="space-y-6">
            @php $s = siteSettings(); @endphp
            <div class="space-y-4 text-slate-600">
                @if($s->contact_email)
                    <p class="flex items-center gap-3">
                        <span class="h-9 w-9 rounded-lg bg-[var(--brand)]/10 text-[var(--brand)] flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                        <a href="mailto:{{ $s->contact_email }}" class="hover:text-[var(--brand)] font-medium">{{ $s->contact_email }}</a>
                    </p>
                @endif
                @if($s->contact_whatsapp)
                    <p class="flex items-center gap-3">
                        <span class="h-9 w-9 rounded-lg bg-[var(--brand)]/10 text-[var(--brand)] flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </span>
                        <span>{{ $s->contact_whatsapp }}</span>
                    </p>
                @endif
                @if($s->contact_address)
                    <p class="flex items-start gap-3">
                        <span class="h-9 w-9 rounded-lg bg-[var(--brand)]/10 text-[var(--brand)] flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                        <span class="leading-relaxed">{{ $s->contact_address }}</span>
                    </p>
                @endif
            </div>

            @if($s->google_maps_embed_url)
                <div class="rounded-xl overflow-hidden border border-slate-200 aspect-video">
                    <iframe src="{{ $s->google_maps_embed_url }}" class="w-full h-full" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            @endif
        </div>

        {{-- Form --}}
        <div class="card p-6 sm:p-8">
            @livewire('contact-form')
        </div>
    </section>
</x-layout>
