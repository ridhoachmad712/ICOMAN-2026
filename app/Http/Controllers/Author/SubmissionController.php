<?php

namespace App\Http\Controllers\Author;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class SubmissionController extends Controller
{
    public function index(): RedirectResponse
    {
        abort_unless(Auth::guard('author')->user()->isPresenter(), 403);

        return redirect()->to(PaperResource::getUrl(panel: 'author'));
    }

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

    public function loa(Submission $submission): View
    {
        $this->authorizeOwner($submission);
        abort_unless($submission->status === 'accepted', 404);

        $submission->load(['authors' => fn ($query) => $query->orderBy('order'), 'edition']);

        return view('author.submissions.loa', compact('submission'));
    }

    public function previewExtendedAbstract(Submission $submission): Response
    {
        $this->authorizeOwner($submission);

        return $this->extendedAbstractPdf($submission);
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
        abort_unless(in_array($submission->status, ['extended_abstract_draft', 'abstract_submitted', 'abstract_approved'], true), 403, 'Extended abstract tidak dapat diubah setelah dikirim ke reviewer.');

        $validated = $request->validate([
            'extended_abstract' => ['required', 'string', 'max:20000'],
        ]);

        DB::transaction(function () use ($submission, $validated): void {
            $submission->update([
                'extended_abstract' => $validated['extended_abstract'],
                'extended_abstract_submitted_at' => now(),
            ]);

            $reviewerIds = $submission->reviewAssignments()
                ->where('phase', 'abstract')
                ->pluck('reviewer_id');

            foreach ($reviewerIds as $reviewerId) {
                $submission->reviewAssignments()->firstOrCreate(
                    ['reviewer_id' => $reviewerId, 'phase' => 'extended_abstract'],
                    ['assigned_at' => now(), 'status' => 'pending'],
                );
            }

            $submission->changeStatus($reviewerIds->isEmpty()
                ? 'extended_abstract_submitted'
                : 'extended_abstract_under_review');
        });

        return back()->with('status', app()->getLocale() === 'id'
            ? 'Extended abstract berhasil dikirim dan menunggu verifikasi reviewer.'
            : 'Your extended abstract was submitted and is awaiting reviewer verification.');
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
