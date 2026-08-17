<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $author = Auth::guard('author')->user();

        $submissions = $author->submissions()
            ->with('topic')
            ->latest('submitted_at')
            ->get();

        $registrations = $author->registrations()->with('registrationFee')->latest()->get();

        return view('author.dashboard', compact('author', 'submissions', 'registrations'));
    }
}
