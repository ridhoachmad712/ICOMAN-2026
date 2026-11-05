<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ModeratorRooms\ModeratorRoomResource;
use App\Models\RoomPresentation;
use App\Models\Schedule;
use Filament\Widgets\Widget;

/** Dashboard moderator: informasi konferensi, room yang dipandu, dan rubrik penilaian. */
class ModeratorDashboard extends Widget
{
    protected string $view = 'filament.widgets.moderator-dashboard';

    protected static bool $isLazy = false;

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isRoomModerator() ?? false;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $edition = currentEdition();

        return [
            'edition' => $edition,
            'settings' => siteSettings(),
            'rooms' => ModeratorRoomResource::myRooms(),
            'roomUrl' => ModeratorRoomResource::getUrl(),
            'criteria' => RoomPresentation::PRESENTER_CRITERIA,
            'scoreMin' => RoomPresentation::SCORE_MIN,
            'scoreMax' => RoomPresentation::SCORE_MAX,
            'schedules' => $edition
                ? Schedule::query()->where('edition_id', $edition->id)->orderBy('day_date')->orderBy('time_start')->orderBy('order')->get()
                : collect(),
        ];
    }
}
