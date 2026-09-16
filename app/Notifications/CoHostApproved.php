<?php

namespace App\Notifications;

use App\Models\CoHost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Kabar persetujuan beserta kode voucher dan langkah pembayarannya. */
class CoHostApproved extends Notification
{
    use Queueable;

    public function __construct(private readonly CoHost $coHost) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isId = app()->getLocale() === 'id';
        $code = $this->coHost->voucher?->code;

        $mail = (new MailMessage)
            ->subject($isId
                ? 'Pengajuan co-host disetujui — '.$this->coHost->institution_name
                : 'Co-host application approved — '.$this->coHost->institution_name)
            ->greeting($isId ? 'Selamat!' : 'Congratulations!')
            ->line($isId
                ? 'Pengajuan '.$this->coHost->institution_name.' sebagai co-host telah disetujui panitia.'
                : 'The application from '.$this->coHost->institution_name.' to become a co-host has been approved.');

        if ($code) {
            $mail->line($isId
                ? 'Kode voucher Anda: '.$code.' — berlaku untuk '.CoHost::FREE_PAPERS.' paper gratis.'
                : 'Your voucher code: '.$code.' — good for '.CoHost::FREE_PAPERS.' free papers.');
        }

        // Kode sengaja disebut sebagai belum aktif: menyembunyikan syarat ini
        // hanya akan membuat penulis mereka bingung saat kodenya ditolak.
        return $mail
            ->line($isId
                ? 'Kode baru dapat dipakai setelah biaya kemitraan lunas. Invoice sudah tersedia di portal Anda.'
                : 'The code becomes usable once the partnership fee is settled. The invoice is waiting in your portal.')
            ->action($isId ? 'Buka Portal Co-host' : 'Open the Co-host Portal', route('filament.author.pages.author-dashboard'));
    }
}
