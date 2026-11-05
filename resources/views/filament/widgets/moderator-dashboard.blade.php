<x-filament-widgets::widget>
    @php
        $muted = 'color:var(--gray-500);';
        $dates = collect([$edition?->start_date, $edition?->end_date])->filter()->map(fn ($date) => $date->translatedFormat('d F Y'))->unique()->implode(' – ');
        $hasZoom = filled($settings->zoom_url) || filled($settings->zoom_meeting_id) || filled($settings->zoom_passcode) || filled($settings->zoom_note);
        $time = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('H:i') : null;
        $cell = 'padding:.5rem .75rem;border-top:1px solid rgba(128,128,128,.25);text-align:left;vertical-align:top;';
    @endphp

    <div style="display:flex;flex-direction:column;gap:1.5rem;">
        <p style="margin:0;font-size:1rem;">Moderator: <strong>{{ auth()->user()->name }}</strong></p>

        <x-filament::section icon="heroicon-o-academic-cap">
            <x-slot name="heading">{{ $settings->conference_name }}</x-slot>
            @if(filled($edition?->theme))
                <x-slot name="description">{{ $edition->theme }}</x-slot>
            @endif

            <div style="display:flex;flex-direction:column;gap:1rem;font-size:.875rem;">
                @if($dates)
                    <p style="margin:0;"><strong>Tanggal:</strong> {{ $dates }}</p>
                @endif

                @if($hasZoom)
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem 1.5rem;">
                        @if(filled($settings->zoom_meeting_id))
                            <span><strong>Meeting ID:</strong> {{ $settings->zoom_meeting_id }}</span>
                        @endif
                        @if(filled($settings->zoom_passcode))
                            <span><strong>Passcode:</strong> {{ $settings->zoom_passcode }}</span>
                        @endif
                        @if(filled($settings->zoom_url))
                            <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-arrow-top-right-on-square" icon-position="after"
                                                href="{{ $settings->zoom_url }}" target="_blank" rel="noopener">
                                Buka Zoom konferensi
                            </x-filament::button>
                        @endif
                    </div>
                    @if(filled($settings->zoom_note))
                        <p style="margin:0;white-space:pre-line;{{ $muted }}">{{ $settings->zoom_note }}</p>
                    @endif
                @endif
            </div>
        </x-filament::section>

        <div style="display:flex;flex-direction:column;gap:.75rem;">
            <h2 style="margin:0;font-size:1rem;font-weight:600;">Room yang Anda pandu</h2>

            @if($rooms->isEmpty())
                <x-filament::section>
                    <p style="margin:0;font-size:.875rem;{{ $muted }}">Belum ada room yang ditugaskan. Admin room akan menunjuk Anda sebagai moderator.</p>
                </x-filament::section>
            @else
                <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,22rem),1fr));">
                    @foreach($rooms as $room)
                        <x-filament::section icon="heroicon-o-presentation-chart-bar">
                            <x-slot name="heading">{{ $room->name }}</x-slot>
                            @if($room->scheduleLabel() || filled($room->meeting_note))
                                <x-slot name="description">{{ collect([$room->scheduleLabel(), $room->meeting_note])->filter()->implode(' · ') }}</x-slot>
                            @endif
                            <x-slot name="afterHeader">@include('filament.moderator.room-status-badge')</x-slot>

                            <div style="display:flex;flex-direction:column;gap:1rem;">
                                @include('filament.moderator.room-progress')
                                <div>
                                    <x-filament::button tag="a" size="sm" href="{{ $roomUrl }}" icon="heroicon-m-arrow-right" icon-position="after">
                                        Buka Room Saya
                                    </x-filament::button>
                                </div>
                            </div>
                        </x-filament::section>
                    @endforeach
                </div>
            @endif
        </div>

        <x-filament::section icon="heroicon-o-clipboard-document-check">
            <x-slot name="heading">Penilaian yang akan dilakukan</x-slot>
            <x-slot name="description">Nilai menjadi acuan. Pemenang ditetapkan oleh admin room.</x-slot>

            <div style="display:flex;flex-direction:column;gap:1.25rem;font-size:.875rem;">
                <ol style="margin:0;padding-left:1.25rem;list-style:decimal;display:flex;flex-direction:column;gap:.25rem;">
                    <li><strong>Absen</strong> setiap presenter: hadir, diwakili co-author, atau tidak hadir.</li>
                    <li><strong>Beri nilai</strong> presenter yang hadir atau diwakili. Yang tidak hadir tidak dinilai.</li>
                    <li><strong>Kirim penilaian</strong> setelah semua selesai. Setelah dikirim, absensi dan nilai terkunci.</li>
                </ol>

                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="padding:.5rem .75rem;text-align:left;">Kriteria presenter</th>
                                <th style="padding:.5rem .75rem;text-align:right;">Bobot</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($criteria as $criterion)
                                <tr>
                                    <td style="{{ $cell }}">{{ $criterion['label'] }}</td>
                                    <td style="{{ $cell }}text-align:right;">{{ $criterion['weight'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p style="margin:0;{{ $muted }}">
                    Setiap kriteria diberi skor {{ $scoreMin }}–{{ $scoreMax }} ({{ $scoreMin }} = sangat kurang, {{ $scoreMax }} = sangat baik).
                    Nilai tertimbang = skor × bobot ÷ {{ $scoreMax }}, dan totalnya 0–100.
                </p>
            </div>
        </x-filament::section>

        @if($schedules->isNotEmpty())
            <x-filament::section icon="heroicon-o-calendar-days" collapsible>
                <x-slot name="heading">Rundown konferensi</x-slot>

                <div style="overflow-x:auto;font-size:.875rem;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="padding:.5rem .75rem;text-align:left;">Waktu</th>
                                <th style="padding:.5rem .75rem;text-align:left;">Sesi</th>
                                <th style="padding:.5rem .75rem;text-align:left;">Pembicara</th>
                                <th style="padding:.5rem .75rem;text-align:left;">Ruang</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($schedules as $schedule)
                                <tr>
                                    <td style="{{ $cell }}white-space:nowrap;">
                                        @if($schedule->day_date){{ \Illuminate\Support\Carbon::parse($schedule->day_date)->translatedFormat('d M') }} · @endif{{ collect([$time($schedule->time_start), $time($schedule->time_end)])->filter()->implode('–') }}
                                    </td>
                                    <td style="{{ $cell }}">{{ $schedule->title }}</td>
                                    <td style="{{ $cell }}">{{ $schedule->speaker_name ?: '—' }}</td>
                                    <td style="{{ $cell }}">{{ $schedule->room ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if(filled($settings->contact_whatsapp) || filled($settings->contact_email))
            <p style="margin:0;font-size:.875rem;{{ $muted }}">
                Butuh bantuan panitia?
                @if(filled($settings->contact_whatsapp)) WhatsApp {{ $settings->contact_whatsapp }}@endif
                @if(filled($settings->contact_whatsapp) && filled($settings->contact_email)) · @endif
                @if(filled($settings->contact_email)) {{ $settings->contact_email }}@endif
            </p>
        @endif
    </div>
</x-filament-widgets::widget>
