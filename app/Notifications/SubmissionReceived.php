<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionReceived extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject("[{$this->submission->submission_number}] Paper submission received")
            ->greeting('Dear '.$notifiable->name.',')
            ->line("We have received your paper submission \"{$this->submission->title}\".")
            ->line('Submission number: '.$this->submission->submission_number)
            ->action('Open Dashboard', url('/author/dashboard'))
            ->line('You will be notified by email when the review status changes.');
    }
}
