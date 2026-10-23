{{-- Akses Zoom konferensi, diisi panitia di Pengaturan → Zoom Konferensi.
     Tautan hanya dikirim ke halaman bila author berhak (Author::canJoinConference());
     presenter yang sudah lunas tapi belum mengumpulkan full paper hanya melihat
     keterangan syaratnya, tanpa tautan. --}}
@php
    $id = app()->getLocale() === 'id';
    $settings = siteSettings();
    $zoomUrl = $settings->zoom_url;
    $eligible = $author->canJoinConference();
    $waitingForFullPaper = ! $eligible && $author->isPresenter() && $author->hasSeminarAccess();
@endphp

@if(filled($zoomUrl) && $eligible)
    <section class="rounded-xl border border-primary-200 bg-primary-50 p-5 dark:border-primary-500/20 dark:bg-primary-500/10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-video-camera" class="mt-0.5 h-6 w-6 shrink-0 text-primary-600 dark:text-primary-400" />
                <div class="min-w-0">
                    <p class="font-semibold text-gray-950 dark:text-white">{{ $id ? 'Akses Zoom konferensi' : 'Conference Zoom access' }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        {{ $id ? 'Gunakan tautan ini untuk bergabung pada hari pelaksanaan. Jangan bagikan kepada orang lain.' : 'Use this link to join on the conference day. Please do not share it.' }}
                    </p>
                    @if(filled($settings->zoom_meeting_id) || filled($settings->zoom_passcode))
                        <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                            @if(filled($settings->zoom_meeting_id))
                                <div class="flex gap-2"><dt class="text-gray-500">Meeting ID</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $settings->zoom_meeting_id }}</dd></div>
                            @endif
                            @if(filled($settings->zoom_passcode))
                                <div class="flex gap-2"><dt class="text-gray-500">Passcode</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $settings->zoom_passcode }}</dd></div>
                            @endif
                        </dl>
                    @endif
                    @if(filled($settings->zoom_note))
                        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $settings->zoom_note }}</p>
                    @endif
                </div>
            </div>
            <x-filament::button tag="a" icon="heroicon-m-arrow-top-right-on-square" icon-position="after"
                                href="{{ $zoomUrl }}" target="_blank" rel="noopener" class="shrink-0">
                {{ $id ? 'Gabung Zoom' : 'Join Zoom' }}
            </x-filament::button>
        </div>
    </section>
@elseif(filled($zoomUrl) && $waitingForFullPaper)
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
        <div class="flex items-start gap-3">
            <x-filament::icon icon="heroicon-o-video-camera" class="mt-0.5 h-6 w-6 shrink-0 text-gray-400" />
            <div>
                <p class="font-semibold text-gray-950 dark:text-white">{{ $id ? 'Akses Zoom konferensi' : 'Conference Zoom access' }}</p>
                <p class="mt-1 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                    {{ $id ? 'Tautan Zoom akan muncul di sini setelah Anda mengumpulkan full paper.' : 'The Zoom link will appear here once you have submitted your full paper.' }}
                </p>
            </div>
        </div>
    </section>
@endif
