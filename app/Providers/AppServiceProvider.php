<?php

namespace App\Providers;

use App\Services\SpySession;
use App\Support\DatabaseTranslationLoader;
use Filament\Notifications\Notification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /** Panggilan Livewire yang hanya mengubah tampilan, bukan data. */
    private const SPY_READ_ONLY_CALLS = [
        'gotoPage', 'nextPage', 'previousPage', 'setPage', 'resetPage', 'sortTable',
        'mountAction', 'unmountAction', 'callSchemaComponentMethod',
        'applyTableFilters', 'removeTableFilter', 'removeTableFilters', 'resetTableFiltersForm', 'resetTableSearch',
    ];

    public function register(): void
    {
        $this->app->singleton(SpySession::class);

        /*
         * Label website dibaca dari lang/ lalu ditimpa suntingan admin, sehingga
         * seluruh pemanggilan __() yang sudah ada ikut bisa disunting tanpa
         * mengubah Blade.
         *
         * Dipasang lewat extend(), bukan singleton(): penyedia terjemahan
         * bawaan Laravel bersifat deferred — ia baru mendaftarkan dirinya saat
         * penerjemah pertama kali dipakai, yang berarti SESUDAH provider ini
         * jalan, dan akan menimpa binding apa pun yang dipasang di sini.
         */
        $this->app->extend('translator', function (Translator $translator, $app): Translator {
            // Loader asli dibungkus, bukan diganti: di dalamnya ada jalur bahasa
            // bawaan framework (pesan validasi) dan namespace milik paket.
            $replacement = new Translator(
                new DatabaseTranslationLoader($app['translation.loader']),
                $translator->getLocale(),
            );
            $replacement->setFallback($translator->getFallback());

            return $replacement;
        });
    }

    public function boot(): void
    {
        // Mode lihat sebagai peserta: tombol yang menyimpan tidak dijalankan.
        // Bila ada yang lolos, kunci database di SpySession tetap menolaknya.
        Livewire::listen('call', function ($component, string $method, $params, $context, $returnEarly): void {
            if (! app(SpySession::class)->isLocked()
                || str_starts_with($method, '$')
                || str_starts_with($method, '__')
                || in_array($method, self::SPY_READ_ONLY_CALLS, true)) {
                return;
            }

            Notification::make()->title('Mode lihat: perubahan tidak disimpan')->warning()->send();
            $returnEarly();
        });
    }
}
