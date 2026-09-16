<?php

namespace App\Notifications;

use App\Models\CoHost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Biaya kemitraan lunas: kode vouchernya terbit.
 *
 * Kode tidak lagi dikirim pada email persetujuan, jadi inilah satu-satunya
 * kabar yang membawanya — tanpa ini co-host harus menebak kapan harus membuka
 * portalnya lagi.
 */
class CoHostActivated extends Notification
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
                ? 'Kemitraan aktif — kode voucher '.$this->coHost->institution_name
                : 'Partnership active — voucher code for '.$this->coHost->institution_name)
            ->greeting($isId ? 'Terima kasih!' : 'Thank you!')
            ->line($isId
                ? 'Biaya kemitraan '.$this->coHost->institution_name.' sudah kami terima. Kemitraan Anda kini aktif.'
                : 'We have received the partnership fee from '.$this->coHost->institution_name.'. Your partnership is now active.');

        if ($code) {
            $mail->line($isId
                ? 'Kode voucher Anda: '.$code
                : 'Your voucher code: '.$code);
        }

        return $mail
            ->line($isId
                ? 'Berikan kode ini kepada penulis dari institusi Anda. Berlaku untuk '.CoHost::FREE_PAPERS.' paper, dimasukkan pada tahap pembayaran registrasi.'
                : 'Share this code with authors from your institution. It covers '.CoHost::FREE_PAPERS.' papers and is entered at the registration payment step.')
            ->action($isId ? 'Buka Portal Co-host' : 'Open the Co-host Portal', route('filament.author.pages.author-dashboard'));
    }
}
