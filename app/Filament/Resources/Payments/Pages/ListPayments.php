<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    /**
     * Tiga pertanyaan yang benar-benar ditanyakan orang ke buku transaksi:
     * uang apa yang masuk, apa yang gagal masuk, dan mana yang saya catat
     * sendiri. Yang terakhir itu paling penting untuk diperiksa ulang — baris
     * manual berarti ada orang yang memutuskan uangnya sudah diterima, bukan
     * gateway yang mengabarkannya.
     */
    public function getTabs(): array
    {
        return [
            'success' => Tab::make('Berhasil')
                ->badge(Payment::where('status', 'success')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'success')),

            'manual' => Tab::make('Dicatat Admin')
                ->badge(Payment::where('method', 'manual')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->where('method', 'manual')),

            'unfinished' => Tab::make('Gagal & Belum Selesai')
                ->badge(Payment::whereIn('status', ['failed', 'initiated'])->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['failed', 'initiated'])),

            'all' => Tab::make('Semua')
                ->badge(Payment::count())
                ->badgeColor('gray'),
        ];
    }
}
