<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\Page;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];

        // Halaman statis utama.
        foreach ([
            'home', 'about', 'venue', 'committee', 'speakers', 'call-for-papers',
            'important-dates', 'registration', 'author-guidelines', 'program', 'faq', 'contact', 'news.index',
        ] as $name) {
            $urls[] = ['loc' => route($name), 'priority' => $name === 'home' ? '1.0' : '0.7'];
        }

        // Halaman CMS dinamis (kecuali yang sudah punya route sendiri).
        Page::where('is_published', true)
            ->whereNotIn('slug', ['about', 'venue', 'publication'])
            ->get()
            ->each(fn (Page $p) => $urls[] = [
                'loc' => route('page', $p->slug),
                'lastmod' => $p->updated_at?->toAtomString(),
                'priority' => '0.5',
            ]);

        // Berita.
        News::where('is_published', true)->get()
            ->each(fn (News $n) => $urls[] = [
                'loc' => route('news.show', $n->slug),
                'lastmod' => $n->updated_at?->toAtomString(),
                'priority' => '0.6',
            ]);

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
