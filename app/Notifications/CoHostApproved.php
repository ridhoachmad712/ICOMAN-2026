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

        $mail = (new MailMessage)
            ->subject($isId
                ? 'Pengajuan co-host disetujui — '.$this->coHost->institution_name
                : 'Co-host application approved — '.$this->coHost->institution_name)
            ->greeting($isId ? 'Selamat!' : 'Congratulations!')
            ->line($isId
                ? 'Pengajuan '.$this->coHost->institution_name.' sebagai co-host telah disetujui panitia.'
                : 'The application from '.$this->coHost->institution_name.' to become a co-host has been approved.');

        // Kodenya sengaja belum disebut di sini. Sebelum biaya kemitraan lunas
        // kode itu ditolak saat dipakai, jadi mengirimkannya sekarang hanya
        // membuat penulis mereka mencobanya dan menyangka ada yang rusak.
        return $mail
            ->line($isId
                ? 'Kode voucher untuk '.CoHost::FREE_PAPERS.' paper gratis terbit setelah biaya kemitraan lunas, dan akan muncul di portal Anda. Invoicenya sudah tersedia di sana.'
                : 'The voucher code for '.CoHost::FREE_PAPERS.' free papers is issued once the partnership fee is settled, and will appear in your portal. The invoice is waiting there.')
            ->action($isId ? 'Buka Portal Co-host' : 'Open the Co-host Portal', route('filament.author.pages.author-dashboard'));
    }
}
