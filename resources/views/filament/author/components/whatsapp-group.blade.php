{{-- Undangan grup WhatsApp peserta. Pemanggil yang memastikan pembayaran
     sudah lunas; komponen ini hanya diam bila panitia belum mengisi tautannya. --}}
@php
    $id = app()->getLocale() === 'id';
    $groupUrl = siteSettings()->whatsapp_group_url;
@endphp

@if(filled($groupUrl))
    <section class="rounded-xl border border-success-200 bg-success-50 p-5 dark:border-success-500/20 dark:bg-success-500/10 {{ $class ?? '' }}">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="mt-0.5 h-6 w-6 shrink-0 text-success-600 dark:text-success-400" />
                <div>
                    <p class="font-semibold text-success-800 dark:text-success-300">
                        {{ $id ? 'Gabung grup WhatsApp peserta' : 'Join the participants\' WhatsApp group' }}
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-success-700 dark:text-success-400">
                        {{ $id
                            ? 'Pembayaran Anda sudah lunas. Informasi teknis, tautan Zoom, dan pengumuman panitia dibagikan di grup ini.'
                            : 'Your payment is complete. Technical information, Zoom links, and committee announcements are shared in this group.' }}
                    </p>
                </div>
            </div>
            <x-filament::button tag="a" color="success" icon="heroicon-m-arrow-top-right-on-square" icon-position="after"
                                href="{{ $groupUrl }}" target="_blank" rel="noopener" class="shrink-0">
                {{ $id ? 'Gabung grup WhatsApp' : 'Join WhatsApp group' }}
            </x-filament::button>
        </div>
    </section>
@endif
