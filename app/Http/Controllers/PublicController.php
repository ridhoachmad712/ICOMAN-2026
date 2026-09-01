<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\Download;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\ImportantDate;
use App\Models\News;
use App\Models\Page;
use App\Models\RegistrationFee;
use App\Models\Schedule;
use App\Models\Speaker;
use App\Models\Sponsor;
use App\Models\Topic;
use Illuminate\Contracts\View\View;

class PublicController extends Controller
{
    private function editionId(): ?int
    {
        return currentEdition()?->id;
    }

    public function home(): View
    {
        $editionId = $this->editionId();

        // Eager-load media untuk hindari N+1 (konversi WebP dipakai di blade).
        $speakers = Speaker::with('media')->where('edition_id', $editionId)->orderBy('order')->get();
        $importantDates = ImportantDate::where('edition_id', $editionId)->orderBy('order')->get();

        return view('public.home', [
            'edition' => currentEdition(),
            'speakers' => $speakers->take(8),
            'importantDates' => $importantDates,
            'nextDeadline' => $importantDates->filter(fn ($d) => $d->date && $d->date->isFuture())->sortBy('date')->first(),
            'topics' => Topic::where('edition_id', $editionId)->orderBy('order')->get(),
            'fees' => RegistrationFee::where('edition_id', $editionId)->orderBy('order')->get(),
            'sponsors' => Sponsor::with('media')->where('edition_id', $editionId)->orderBy('order')->get()->groupBy('tier'),
            'galleries' => Gallery::with('media')->where('edition_id', $editionId)->orderBy('order')->limit(6)->get(),
            'faqs' => Faq::where('edition_id', $editionId)->orWhereNull('edition_id')->orderBy('order')->limit(5)->get(),
            'aboutPage' => $this->publishedPage('about'),
            'publicationPage' => $this->publishedPage('publication'),
            'stats' => [
                'speakers' => $speakers->count(),
                'topics' => Topic::where('edition_id', $editionId)->count(),
                'countries' => $speakers->pluck('country')->map(fn ($c) => countryCode($c))->filter()->unique()->count(),
            ],
            'latestNews' => News::with('media')
                ->where('is_published', true)
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(),
        ]);
    }

    public function speakers(): View
    {
        return view('public.speakers', [
            'speakers' => Speaker::with('media')->where('edition_id', $this->editionId())->orderBy('order')->get(),
        ]);
    }

    public function committee(): View
    {
        $committees = Committee::where('edition_id', $this->editionId())
            ->orderBy('order')
            ->get()
            ->groupBy('category');

        return view('public.committee', compact('committees'));
    }

    public function callForPapers(): View
    {
        $editionId = $this->editionId();

        return view('public.call-for-papers', [
            'page' => $this->publishedPage('call-for-papers'),
            'topics' => Topic::where('edition_id', $editionId)->orderBy('order')->get(),
            'templates' => Download::where('edition_id', $editionId)
                ->orWhereNull('edition_id')
                ->orderBy('order')->get(),
        ]);
    }

    public function importantDates(): View
    {
        return view('public.important-dates', [
            'importantDates' => ImportantDate::where('edition_id', $this->editionId())->orderBy('order')->get(),
        ]);
    }

    public function registration(): View
    {
        return view('public.registration', [
            'fees' => RegistrationFee::where('edition_id', $this->editionId())->orderBy('order')->get(),
        ]);
    }

    public function downloads(): View
    {
        return view('public.downloads', [
            'downloads' => Download::where('edition_id', $this->editionId())
                ->orWhereNull('edition_id')
                ->orderBy('order')->get(),
        ]);
    }

    public function schedule(): View
    {
        $schedules = Schedule::where('edition_id', $this->editionId())
            ->orderBy('day_date')
            ->orderBy('time_start')
            ->get()
            ->groupBy(fn ($s) => optional($s->day_date)->format('Y-m-d'));

        return view('public.schedule', compact('schedules'));
    }

    public function faq(): View
    {
        return view('public.faq', [
            'faqs' => Faq::where('edition_id', $this->editionId())
                ->orWhereNull('edition_id')
                ->orderBy('order')->get(),
        ]);
    }

    public function contact(): View
    {
        return view('public.contact');
    }

    /** Halaman CMS dinamis (About, Venue, dsb.) by slug — degrade halus jika belum ada. */
    public function page(string $slug): View
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->first();

        return view('public.page', [
            'page' => $page,
            'fallbackTitle' => ucwords(str_replace('-', ' ', $slug)),
        ]);
    }

    private function publishedPage(string $slug): ?Page
    {
        return Page::where('slug', $slug)->where('is_published', true)->first();
    }
}
