<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\Download;
use App\Models\Faq;
use App\Models\ImportantDate;
use App\Models\Page;
use App\Models\RegistrationFee;
use App\Models\Schedule;
use App\Models\Speaker;
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
        // Susunan beranda ditentukan blok-blok di Penyusun Halaman, dan tiap
        // blok menarik datanya sendiri. Yang tersisa di sini hanya bahan
        // structured data untuk mesin pencari.
        return view('public.home', [
            'edition' => currentEdition(),
            'aboutPage' => $this->publishedPage('about'),
        ]);
    }

    public function speakers(): View
    {
        return view('public.speakers', [
            'speakers' => Speaker::where('is_published', true)->with('media')->where('edition_id', $this->editionId())->orderBy('order')->get(),
        ]);
    }

    public function committee(): View
    {
        $committees = Committee::where('is_published', true)->where('edition_id', $this->editionId())
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
        $page = $this->publishedPage($slug);
        abort_unless($page, 404);

        return view('public.page', [
            'page' => $page,
            'fallbackTitle' => ucwords(str_replace('-', ' ', $slug)),
        ]);
    }

    private function publishedPage(string $slug): ?Page
    {
        return Page::publiclyVisible()->where('slug', $slug)->first();
    }
}
