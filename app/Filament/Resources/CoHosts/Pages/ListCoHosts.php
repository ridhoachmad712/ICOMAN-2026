<?php

namespace App\Filament\Resources\CoHosts\Pages;

use App\Filament\Resources\CoHosts\CoHostResource;
use App\Models\CoHost;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCoHosts extends ListRecords
{
    protected static string $resource = CoHostResource::class;

    /** Yang menunggu tinjauan didahulukan; itu satu-satunya yang menuntut tindakan. */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Menunggu tinjauan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(CoHost::where('status', 'pending')->count()),
            'approved' => Tab::make('Disetujui')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved')),
            'rejected' => Tab::make('Ditolak')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected')),
            'all' => Tab::make('Semua'),
        ];
    }
}
