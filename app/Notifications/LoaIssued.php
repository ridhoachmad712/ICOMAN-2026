<?php

namespace App\Notifications;

use App\Filament\Author\Resources\Papers\PaperResource;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoaIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[{$this->submission->submission_number}] Letter of Acceptance issued")
            ->greeting('Dear '.$notifiable->name.',')
            ->line("The Letter of Acceptance for your paper \"{$this->submission->title}\" ({$this->submission->submission_number}) has been issued and is now available in your author portal.")
            ->line('Your next step is to complete the presenter registration payment.');

        if ($this->submission->sinta3_offered) {
            $mail->line('Your paper has also been offered publication in a SINTA 3 journal. You may choose this option (with an additional fee) when completing your registration payment.');
        }

        return $mail
            ->action('Open Paper', PaperResource::getUrl('view', ['record' => $this->submission], panel: 'author'))
            ->line('Thank you for your contribution to the conference.');
    }
}
