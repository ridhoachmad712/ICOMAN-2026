<?php

namespace App\Notifications;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $labels = [
            'extended_abstract_draft' => 'extended abstract draft',
            'abstract_submitted' => 'abstract received',
            'abstract_under_review' => 'abstract under review',
            'abstract_approved' => 'abstract review passed',
            'extended_abstract_submitted' => 'extended abstract received',
            'extended_abstract_under_review' => 'extended abstract under verification',
            'accepted' => 'accepted',
            'rejected' => 'not accepted',
        ];

        $statusLabel = $labels[$this->submission->status] ?? $this->submission->status;

        $mail = (new MailMessage)
            ->subject("[{$this->submission->submission_number}] Submission status: ".ucfirst($statusLabel))
            ->greeting('Dear '.$notifiable->name.',')
            ->line("The status of your submission \"{$this->submission->title}\" ({$this->submission->submission_number}) has been updated to: **".ucfirst($statusLabel).'**.');

        match ($this->submission->status) {
            'extended_abstract_draft' => $mail->line('Continue writing all five sections of your extended abstract in the author portal.'),
            'abstract_submitted' => $mail->line('We received your abstract. The next action belongs to the reviewer; no action is required from you now.'),
            'abstract_under_review' => $mail->line('A reviewer is processing your abstract. You can monitor progress in the author portal.'),
            'abstract_approved' => $mail->line('Your abstract passed review. Your next action is to complete registration and payment in the author portal.'),
            'extended_abstract_submitted', 'extended_abstract_under_review' => $mail->line('Your extended abstract is being verified by the reviewer. No further action is required from you now.'),
            'accepted' => $mail->line('Congratulations! Your paper is accepted. No further action is required, and your acceptance letter is available in the portal.'),
            'rejected' => $mail->line('The review result and reviewer feedback are available on the paper detail page.'),
            default => null,
        };

        return $mail
            ->action('Open Paper', PaperResource::getUrl('view', ['record' => $this->submission], panel: 'author'))
            ->line('Thank you for your contribution to the conference.');
    }
}
