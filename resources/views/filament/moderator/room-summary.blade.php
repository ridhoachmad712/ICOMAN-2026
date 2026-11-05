{{-- Dibaca ulang setiap render, supaya kemajuan langsung berubah setelah absen atau menilai. --}}
<div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,22rem),1fr));">
    @foreach(\App\Filament\Resources\ModeratorRooms\ModeratorRoomResource::myRooms() as $room)
        <x-filament::section icon="heroicon-o-video-camera">
            <x-slot name="heading">{{ $room->name }}</x-slot>
            @if($room->scheduleLabel() || filled($room->meeting_note))
                <x-slot name="description">{{ collect([$room->scheduleLabel(), $room->meeting_note])->filter()->implode(' · ') }}</x-slot>
            @endif
            <x-slot name="afterHeader">@include('filament.moderator.room-status-badge')</x-slot>

            <div style="display:flex;flex-direction:column;gap:1rem;">
                @include('filament.moderator.room-progress')

                @if(filled($room->meeting_url))
                    <div>
                        <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-arrow-top-right-on-square" icon-position="after"
                                            href="{{ $room->meeting_url }}" target="_blank" rel="noopener">
                            Buka tautan room
                        </x-filament::button>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endforeach
</div>
