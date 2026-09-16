<?php

namespace App\Filament\Author\Pages;

use App\Services\AuthorJourney;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class AuthorDashboard extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $title = 'Dashboard';

    protected string $view = 'filament.author.pages.dashboard';

    public function getTitle(): string|Htmlable
    {
        return app()->getLocale() === 'id' ? 'Dashboard Author' : 'Author Dashboard';
    }

    public function getViewData(): array
    {
        $author = Filament::auth()->user();

        // Institusi co-host punya perjalanan sendiri: tidak mengirim paper,
        // melainkan menunggu tinjauan lalu mengelola kuota vouchernya.
        if ($author?->isCoHost()) {
            return $this->coHostData($author);
        }

        $submissions = $author->submissions()
            ->with(['topic', 'reviewAssignments.review'])
            ->latest('submitted_at')
            ->get();
        $registrations = $author->registrations()->with(['registrationFee', 'submission'])->latest()->get();
        $journey = app(AuthorJourney::class);

        return [
            'author' => $author,
            'submissions' => $submissions,
            'registrations' => $registrations,
            'nextAction' => $journey->nextAction($author, $submissions, $registrations),
            'journeySteps' => $journey->timeline($author, $submissions, $registrations),
            'recentUpdates' => $journey->recentUpdates($submissions, $registrations),
            'showPayments' => $journey->shouldShowPayments($author, $submissions, $registrations),
        ];
    }

    /** @return array<string, mixed> */
    private function coHostData($author): array
    {
        $coHost = $author->coHost()->with(['voucher.redemptions.author', 'sponsor'])->first();

        return [
            'author' => $author,
            'coHost' => $coHost,
            'coHostRegistration' => $coHost?->registration(),
            'submissions' => collect(),
            'registrations' => collect(),
            'nextAction' => null,
            'journeySteps' => [],
            'recentUpdates' => collect(),
            'showPayments' => false,
        ];
    }
}
