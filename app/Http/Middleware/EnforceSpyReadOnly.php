<?php

namespace App\Http\Middleware;

use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Services\SpySession;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Mengunci setiap permintaan portal selama panitia melihat akun peserta. */
class EnforceSpyReadOnly
{
    public function __construct(private SpySession $spy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->spy->active()) {
            return $next($request);
        }

        if (! $this->spy->isValid()) {
            $this->spy->stop();

            return $next($request);
        }

        if ($this->isAdminRequest($request) || $request->routeIs('spy.stop')) {
            return $next($request);
        }

        // Keluar dari portal berarti mengakhiri sesi ini, bukan logout milik peserta.
        if ($request->routeIs('filament.author.auth.logout')) {
            $this->spy->stop();

            return redirect()->to(ListAuthors::getUrl(panel: 'admin'));
        }

        if (! $request->isMethodSafe() && ! $this->isLivewireUpdate($request)) {
            Notification::make()->title('Mode lihat: perubahan tidak disimpan')->warning()->send();

            return back();
        }

        $this->spy->lock();

        try {
            return $next($request);
        } finally {
            $this->spy->unlock();
        }
    }

    private function isLivewireUpdate(Request $request): bool
    {
        return (bool) $request->route()?->named('*livewire.update');
    }

    private function isAdminRequest(Request $request): bool
    {
        if ($request->is('admin', 'admin/*')) {
            return true;
        }

        if (! $this->isLivewireUpdate($request)) {
            return false;
        }

        // Jalur komponen ada di snapshot yang ditandatangani Livewire.
        $components = collect($request->input('components', []));

        return $components->isNotEmpty() && $components->every(function ($component): bool {
            $path = json_decode($component['snapshot'] ?? '', true)['memo']['path'] ?? '';

            return $path === 'admin' || str_starts_with($path, 'admin/');
        });
    }
}
