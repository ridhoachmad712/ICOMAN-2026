<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Services\SpySession;
use Illuminate\Http\RedirectResponse;

class SpySessionController extends Controller
{
    public function stop(SpySession $spy): RedirectResponse
    {
        $spy->stop();

        return redirect()->to(ListAuthors::getUrl(panel: 'admin'));
    }
}
