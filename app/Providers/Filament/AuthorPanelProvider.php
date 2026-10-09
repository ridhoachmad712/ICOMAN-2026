<?php

namespace App\Providers\Filament;

use App\Filament\Author\Auth\Login;
use App\Filament\Author\Pages\AuthorDashboard;
use App\Filament\Author\Pages\AuthorProfile;
use App\Filament\Author\Resources\Papers\PaperResource;
use App\Http\Middleware\SetLocale;
use App\Settings\SiteSettings;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AuthorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $brand = rescue(fn () => siteSettings()->brandColor(), null, false) ?: SiteSettings::DEFAULT_BRAND;
        $brand2 = rescue(fn () => siteSettings()->brandColor2(), null, false) ?: SiteSettings::DEFAULT_BRAND_2;
        $conference = rescue(fn () => siteSettings()->conference_name, null, false) ?: config('app.name', 'ICOMAN 2026');

        return $panel
            ->id('author')
            ->path('author')
            ->login(Login::class)
            // Tanpa reset mandiri: author yang lupa password dibantu admin
            // lewat menu Akun Author di panel admin.
            ->profile(AuthorProfile::class, isSimple: false)
            ->authGuard('author')
            ->authPasswordBroker('authors')
            ->brandName($conference.' · Author Portal')
            ->colors(['primary' => Color::hex($brand)])
            ->darkMode(false)
            ->viteTheme('resources/css/filament/author/theme.css')
            // Warna brand disuntikkan ke halaman panel supaya markup kustom
            // (kerangka login split-screen) memakai warna yang sama dengan website.
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => new HtmlString(
                '<style>:root{--brand:'.e($brand).';--brand-2:'.e($brand2).';}</style>'
            ))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.author.auth.login-after'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.author.components.language-switcher'))
            ->topNavigation()
            ->discoverResources(in: app_path('Filament/Author/Resources'), for: 'App\Filament\Author\Resources')
            ->discoverPages(in: app_path('Filament/Author/Pages'), for: 'App\Filament\Author\Pages')
            ->pages([AuthorDashboard::class])
            ->navigationItems([
                NavigationItem::make(fn () => app()->getLocale() === 'id' ? 'Paper saya' : 'My paper')
                    ->url(function (): string {
                        $author = auth('author')->user();
                        $edition = currentEdition();
                        $paper = $edition ? $author?->submissions()->where('edition_id', $edition->id)->first() : null;

                        return $paper
                            ? PaperResource::getUrl(PaperResource::canEdit($paper) ? 'extended-abstract' : 'view', ['record' => $paper], panel: 'author')
                            : PaperResource::getUrl('create', panel: 'author');
                    })
                    ->visible(fn (): bool => (bool) auth('author')->user()?->isPresenter() && (bool) currentEdition())
                    ->icon('heroicon-o-document-text')
                    ->sort(2),
                NavigationItem::make(fn () => app()->getLocale() === 'id' ? 'Panduan' : 'Guidelines')
                    ->url(fn () => route('author-guidelines', ['lang' => app()->getLocale()]))
                    ->icon('heroicon-o-book-open')
                    ->sort(3),
                NavigationItem::make(fn () => app()->getLocale() === 'id' ? 'Kembali ke Website' : 'Back to Website')
                    ->url(fn () => route('home'))
                    ->icon('heroicon-o-arrow-left')
                    ->sort(100),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                SetLocale::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
