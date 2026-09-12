<?php

namespace App\Providers;

use App\Support\DatabaseTranslationLoader;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
            $replacement = new Translator(
                new DatabaseTranslationLoader($app['files'], $app['path.lang']),
                $translator->getLocale(),
            );
            $replacement->setFallback($translator->getFallback());

            return $replacement;
        });
    }

    public function boot(): void
    {
        //
    }
}
