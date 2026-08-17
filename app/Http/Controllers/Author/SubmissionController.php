<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    public function create(): View
    {
        return view('author.submissions.create');
    }

    public function show(Submission $submission): View
    {
        $this->authorizeOwner($submission);

        $submission->load(['topic', 'authors' => fn ($q) => $q->orderBy('order')]);

        // Review yang sudah selesai — hanya komentar untuk author (bukan catatan internal komite).
        $reviews = \App\Models\Review::query()
            ->whereHas('assignment', fn ($q) => $q->where('submission_id', $submission->id))
            ->whereNotNull('submitted_at')
            ->get();

        return view('author.submissions.show', compact('submission', 'reviews'));
    }

    public function uploadCameraReady(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($submission);

        abort_unless($submission->status === 'accepted', 403, 'Camera-ready hanya untuk paper yang diterima.');

        $request->validate([
            'camera_ready' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
        ]);

        $submission->clearMediaCollection('camera_ready');
        $submission->addMediaFromRequest('camera_ready')->toMediaCollection('camera_ready');

        return back()->with('status', __('Camera-ready berhasil diunggah.'));
    }

    private function authorizeOwner(Submission $submission): void
    {
        abort_unless($submission->author_id === Auth::guard('author')->id(), 403);
    }
}
