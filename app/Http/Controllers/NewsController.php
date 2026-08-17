<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Contracts\View\View;

class NewsController extends Controller
{
    public function index(): View
    {
        $news = News::where('is_published', true)
            ->orderByDesc('published_at')
            ->paginate(9);

        return view('public.news.index', compact('news'));
    }

    public function show(string $slug): View
    {
        $item = News::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $related = News::where('is_published', true)
            ->where('id', '!=', $item->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('public.news.show', compact('item', 'related'));
    }
}
