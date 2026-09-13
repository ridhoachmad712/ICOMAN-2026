<?php

namespace App\Filament\Author\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Halaman login portal author dengan kerangka split-screen — identitas
 * konferensi di kiri, formulir di kanan — supaya sejalan dengan halaman
 * pendaftaran, bukan kartu polos bawaan Filament.
 */
class Login extends BaseLogin
{
    protected static string $layout = 'components.author-auth-split';

    /**
     * Nama konferensi sudah tampil besar di panel kiri; mengulanginya di atas
     * formulir membuat identitas yang sama muncul tiga kali berturut-turut.
     */
    public function hasLogo(): bool
    {
        return false;
    }
}
