<?php

namespace App\Http\Controllers\Author;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class SubmissionController extends Controller
{
    public function create(): RedirectResponse
    {
        $author = Auth::guard('author')->user();
        abort_unless($author->isPresenter(), 403, 'Submission abstrak hanya tersedia untuk akun presenter.');

        $existing = $author->submissions()
            ->when(currentEdition(), fn ($query, $edition) => $query->where('edition_id', $edition->id))
            ->latest('submitted_at')
            ->first();

        if ($existing) {
            return redirect()->to(PaperResource::getUrl('view', ['record' => $existing], panel: 'author'))
                ->with('status', app()->getLocale() === 'id'
                    ? 'Setiap akun hanya dapat mengirim satu abstrak pada edisi konferensi ini.'
                    : 'Each account may submit only one abstract in this conference edition.');
        }

        return redirect()->to(PaperResource::getUrl('create', panel: 'author'));
    }

    public function show(Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($submission);

        return redirect()->to(PaperResource::getUrl('view', ['record' => $submission], panel: 'author'));
    }

    public function loa(Submission $submission): Response
    {
        $this->authorizeOwner($submission);
        abort_unless($submission->status === 'accepted' && $submission->isLoaIssued(), 404);

        $submission->load(['authors' => fn ($query) => $query->orderBy('order'), 'edition']);

        // LOA di-generate otomatis sebagai PDF resmi.
        return Pdf::loadView('pdf.loa', compact('submission'))
            ->setPaper('a4')
            ->stream($submission->submission_number.'-LOA.pdf');
    }

    /** Presenter mengunggah naskah lengkap (full paper) setelah pembayaran terverifikasi. */
    public function submitFullPaper(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($submission);

        abort_unless($submission->canSubmitFullPaper(), 403, app()->getLocale() === 'id'
            ? 'Full paper hanya dapat dikirim setelah LOA terbit dan pembayaran registrasi terverifikasi.'
            : 'The full paper can only be submitted after the LOA is issued and the registration payment is verified.');

        $request->validate([
            'full_paper' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
        ]);

        app(\App\Services\ConferenceDeadlines::class)->assertOpen('full_paper', $submission->edition_id, 'full_paper');
        // Retain previous private versions. A failed upload leaves the old file intact.
        $submission->addMediaFromRequest('full_paper')->toMediaCollection('camera_ready');
        $submission->forceFill(['full_paper_submitted_at' => now()])->save();

        return back()->with('status', app()->getLocale() === 'id'
            ? 'Naskah lengkap (full paper) berhasil dikirim.'
            : 'Your full paper has been submitted successfully.');
    }

    /** Unduh full paper milik sendiri. */
    public function downloadFullPaper(Submission $submission): Response
    {
        $this->authorizeOwner($submission);

        $media = $submission->fullPaperMedia();
        abort_unless($media, 404);

        return response()->download($media->getPath(), $media->file_name);
    }

    public function previewExtendedAbstract(Submission $submission): Response
    {
        $this->authorizeOwner($submission);

        return $this->extendedAbstractPdf($submission);
    }

    public function downloadFullPaperForAdmin(Submission $submission): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user && ($user->hasAnyRole(['superadmin', 'admin_registrasi', 'content_admin'])
            || $submission->reviewAssignments()->where('reviewer_id', $user->id)->exists()), 403);
        $media = $submission->fullPaperMedia();
        abort_unless($media, 404);

        return response()->download($media->getPath(), $media->file_name)->header('Cache-Control', 'private, no-store');
    }

    public function previewExtendedAbstractForAdmin(Submission $submission): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 403);

        $mayReview = $submission->reviewAssignments()->where('reviewer_id', $user->id)->exists();
        $mayManage = $user->hasAnyRole(['superadmin', 'admin_registrasi', 'content_admin']);
        abort_unless($mayReview || $mayManage, 403);

        return $this->extendedAbstractPdf($submission);
    }

    public function submitExtendedAbstract(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeOwner($submission);
        abort_unless(in_array($submission->status, Submission::AUTHOR_EDITABLE_STATUSES, true), 403, 'Abstract tidak dapat diubah setelah dikirim ke reviewer.');
        app(\App\Services\ConferenceDeadlines::class)->assertOpen($submission->status === 'revision_required' ? 'revision' : 'abstract', $submission->edition_id, 'abstract');

        $validated = $request->validate([
            'abstract' => ['required', 'string', 'max:6000'],
        ]);

        $wordCount = count(preg_split('/\s+/', trim($validated['abstract']), -1, PREG_SPLIT_NO_EMPTY));
        if ($wordCount < Submission::ABSTRACT_MIN_WORDS || $wordCount > Submission::ABSTRACT_MAX_WORDS) {
            throw ValidationException::withMessages([
                'abstract' => app()->getLocale() === 'id'
                    ? 'Abstract wajib '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' kata.'
                    : 'The abstract must be '.Submission::ABSTRACT_MIN_WORDS.'–'.Submission::ABSTRACT_MAX_WORDS.' words.',
            ]);
        }

        DB::transaction(function () use ($submission, $validated): void {
            $submission->snapshot('abstract_submitted');
            $submission->update([
                'abstract' => $validated['abstract'],
                'extended_abstract_submitted_at' => now(),
            ]);

            // Siklus revisi: reviewer yang sudah ada direset ke pending untuk
            // menilai ulang. Kiriman pertama (tanpa reviewer) → submitted.
            $assignments = $submission->reviewAssignments()->where('phase', 'extended_abstract');
            $hasReviewers = (clone $assignments)->exists();
            (clone $assignments)->update(['status' => 'pending']);

            $submission->changeStatus($hasReviewers
                ? 'extended_abstract_under_review'
                : 'extended_abstract_submitted');
        });

        return back()->with('status', app()->getLocale() === 'id'
            ? 'Abstract berhasil dikirim dan menunggu verifikasi reviewer.'
            : 'Your abstract was submitted and is awaiting reviewer verification.');
    }

    private function authorizeOwner(Submission $submission): void
    {
        abort_unless($submission->author_id === Auth::guard('author')->id(), 403);
    }

    private function extendedAbstractPdf(Submission $submission): Response
    {
        $submission->loadMissing(['edition', 'topic', 'authors']);

        return Pdf::loadView('pdf.extended-abstract', compact('submission'))
            ->setPaper('a4')
            ->stream($submission->submission_number.'-extended-abstract.pdf');
    }
}
