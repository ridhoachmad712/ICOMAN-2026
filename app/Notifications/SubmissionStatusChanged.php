<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $labels = [
            'submitted' => 'received',
            'under_review' => 'under review',
            'revision_required' => 'requires revision',
            'accepted' => 'accepted',
            'rejected' => 'not accepted',
        ];

        $statusLabel = $labels[$this->submission->status] ?? $this->submission->status;

        $mail = (new MailMessage)
            ->subject("[{$this->submission->submission_number}] Paper status: ".ucfirst($statusLabel))
            ->greeting('Dear '.$notifiable->name.',')
            ->line("The status of your paper \"{$this->submission->title}\" ({$this->submission->submission_number}) has been updated to: **".ucfirst($statusLabel).'**.');

        if ($this->submission->status === 'revision_required') {
            $mail->line('Please review the reviewer comments in your dashboard and upload a revised version before the deadline.');
        } elseif ($this->submission->status === 'accepted') {
            $mail->line('Congratulations! Please prepare and upload your camera-ready version.');
        }

        return $mail
            ->action('Open Dashboard', url('/author/dashboard'))
            ->line('Thank you for your contribution to the conference.');
    }
}
