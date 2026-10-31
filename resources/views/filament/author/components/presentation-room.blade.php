{{-- Room presentasi paper presenter, diatur admin room di menu Conference.
     Baru tampil setelah room dipublikasikan; tautan room hanya untuk presenter
     yang berhak mengikuti Zoom (Author::canJoinConference()). --}}
@php
    $id = app()->getLocale() === 'id';
    $slots = $author->isPresenter() && currentEdition()
        ? \App\Models\RoomPresentation::query()
            ->whereHas('submission', fn ($paper) => $paper->where('author_id', $author->id)->where('edition_id', currentEdition()->id))
            ->whereHas('room', fn ($room) => $room->where('is_published', true))
            ->with(['room', 'submission'])
            ->get()
        : collect();
    $eligible = $slots->isNotEmpty() && $author->canJoinConference();
@endphp

@foreach($slots as $slot)
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-presentation-chart-bar" class="mt-0.5 h-6 w-6 shrink-0 text-primary-600 dark:text-primary-400" />
                <div class="min-w-0">
                    <p class="font-semibold text-gray-950 dark:text-white">{{ $id ? 'Jadwal presentasi Anda' : 'Your presentation schedule' }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $slot->submission->submission_number }} · {{ $slot->submission->title }}</p>
                    <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                        <div class="flex gap-2"><dt class="text-gray-500">Room</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $slot->room->name }}</dd></div>
                        <div class="flex gap-2"><dt class="text-gray-500">{{ $id ? 'Urutan tampil' : 'Presentation order' }}</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $slot->order }}</dd></div>
                        @if($slot->room->starts_at)
                            <div class="flex gap-2 sm:col-span-2"><dt class="text-gray-500">{{ $id ? 'Waktu' : 'Time' }}</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $slot->room->scheduleLabel() }}</dd></div>
                        @endif
                        @if(filled($slot->room->meeting_note))
                            <div class="flex gap-2 sm:col-span-2"><dt class="text-gray-500">{{ $id ? 'Keterangan' : 'Note' }}</dt><dd class="text-gray-950 dark:text-white">{{ $slot->room->meeting_note }}</dd></div>
                        @endif
                    </dl>
                    <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                        {{ $id ? 'Moderator akan mencatat kehadiran saat presentasi. Kehadiran menjadi syarat terbitnya sertifikat presenter.' : 'The moderator will record attendance during the session. Attendance is required for the presenter certificate.' }}
                    </p>
                </div>
            </div>
            @if($eligible && filled($slot->room->meeting_url))
                <x-filament::button tag="a" icon="heroicon-m-arrow-top-right-on-square" icon-position="after"
                                    href="{{ $slot->room->meeting_url }}" target="_blank" rel="noopener" class="shrink-0">
                    {{ $id ? 'Masuk room' : 'Join room' }}
                </x-filament::button>
            @endif
        </div>
    </section>
@endforeach
