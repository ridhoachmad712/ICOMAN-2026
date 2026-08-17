<x-layout :title="__('nav.contact')">
    <x-page-header :title="__('site.contact_us')" />

    <section class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-14 grid gap-10 md:grid-cols-2">
        {{-- Info --}}
        <div class="space-y-6">
            @php $s = siteSettings(); @endphp
            <div class="space-y-3 text-slate-600">
                @if($s->contact_email)
                    <p class="flex items-center gap-3"><span class="text-[var(--brand)]">✉</span> <a href="mailto:{{ $s->contact_email }}" class="hover:text-[var(--brand)]">{{ $s->contact_email }}</a></p>
                @endif
                @if($s->contact_whatsapp)
                    <p class="flex items-center gap-3"><span class="text-[var(--brand)]">☎</span> {{ $s->contact_whatsapp }}</p>
                @endif
                @if($s->contact_address)
                    <p class="flex items-start gap-3"><span class="text-[var(--brand)]">📍</span> <span>{{ $s->contact_address }}</span></p>
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
