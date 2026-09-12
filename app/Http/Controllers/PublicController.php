<?php

namespace App\Http\Controllers;

use App\Models\Page;
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

    /*
     * Halaman terstruktur tidak lagi menyiapkan data di sini: isinya
     * disusun dari blok di Penyusun Halaman, dan tiap blok menarik datanya
     * sendiri lewat SectionContent.
     */
    public function speakers(): View
    {
        return view('public.speakers');
    }

    public function committee(): View
    {
        return view('public.committee');
    }

    public function callForPapers(): View
    {
        return view('public.call-for-papers');
    }

    public function importantDates(): View
    {
        return view('public.important-dates');
    }

    public function registration(): View
    {
        return view('public.registration');
    }

    public function downloads(): View
    {
        return view('public.downloads');
    }

    public function schedule(): View
    {
        return view('public.schedule');
    }

    public function faq(): View
    {
        return view('public.faq');
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
