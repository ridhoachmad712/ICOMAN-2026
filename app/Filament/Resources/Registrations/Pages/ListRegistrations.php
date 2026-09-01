<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Filament\Widgets\RegistrationStats;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

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

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Registrasi')
                ->badge(Registration::count())
                ->badgeColor('gray'),

            'pending_verification' => Tab::make('Perlu Verifikasi Bukti')
                ->badge(Registration::where('status', 'pending_verification')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'pending_verification')),

            'paid' => Tab::make('Lunas (Paid)')
                ->badge(Registration::where('status', 'paid')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'paid')),

            'pending' => Tab::make('Menunggu Pembayaran')
                ->badge(Registration::where('status', 'pending')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'pending')),

            'failed' => Tab::make('Gagal / Batal')
                ->badge(Registration::where('status', 'failed')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'failed')),
        ];
    }
}
