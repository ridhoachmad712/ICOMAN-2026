<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Filament\Widgets\RegistrationStats;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->streamDownload(function () {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['ID', 'Participant', 'Email', 'Category', 'Amount', 'Method', 'Status', 'Paid At']);

                    Registration::with(['author', 'registrationFee'])->orderBy('id')->chunk(200, function ($rows) use ($out) {
                        foreach ($rows as $r) {
                            fputcsv($out, [
                                $r->id,
                                $r->author?->name,
                                $r->author?->email,
                                $r->registrationFee?->category,
                                $r->amount,
                                $r->payment_method,
                                $r->status,
                                $r->paid_at?->format('Y-m-d H:i'),
                            ]);
                        }
                    });

                    fclose($out);
                }, 'registrations-'.now()->format('Ymd-His').'.csv')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RegistrationStats::class,
        ];
    }
}
