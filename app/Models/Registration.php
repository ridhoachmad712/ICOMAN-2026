<?php

namespace App\Models;

use App\Notifications\CoHostActivated;
use App\Services\ConferenceDeadlines;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Registration extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'edition_id',
        'author_id',
        'registration_fee_id',
        'submission_id',
        'voucher_id',
        'payment_method',
        'installment_plan',
        'amount',
        'discount_amount',
        'status',
        'gateway_transaction_id',
        'gateway_payload',
        'paid_at',
        'pricing_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'installment_plan' => 'boolean',
            'discount_amount' => 'decimal:2',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
            'pricing_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        /*
         * Kemitraan co-host menyala saat invoicenya lunas: vouchernya aktif dan
         * logonya tampil di website. Dipasang sebagai event model, bukan di satu
         * tempat pembayaran, karena status lunas bisa datang dari webhook
         * BorderPay, penyelarasan manual, maupun verifikasi panitia di admin.
         */
        static::updated(function (Registration $registration): void {
            if (! $registration->wasChanged('status') || $registration->status !== 'paid') {
                return;
            }

            $coHost = CoHost::where('author_id', $registration->author_id)
                ->where('edition_id', $registration->edition_id)
                ->approved()
                ->first();

            if (! $coHost) {
                return;
            }

            $coHost->voucher?->update(['is_active' => true]);
            $coHost->sponsor?->update(['is_published' => true]);

            // Kodenya baru terbit sekarang, dan tidak pernah dikirim
            // sebelumnya, jadi inilah satu-satunya kabar yang membawanya.
            $coHost->author?->notify(new CoHostActivated($coHost->fresh()));
        });

        static::creating(function (Registration $registration): void {
            if ($registration->pricing_snapshot !== null) {
                return;
            }
            $fee = $registration->registrationFee;
            $registration->pricing_snapshot = [
                'base_amount' => $registration->amount,
                'addon_amount' => 0,
                'quoted_addon_amount' => (int) rescue(fn () => siteSettings()->sinta3_fee, 0, false),
                'currency' => $fee?->currency ?? 'IDR',
                'category' => $fee?->getTranslations('category') ?? ['en' => 'Registration', 'id' => 'Registrasi'],
                'journal_target' => 'regular',
                'legacy' => false,
            ];
        });
    }

    public function priceDetails(): array
    {
        return $this->pricing_snapshot ?? [
            'base_amount' => $this->amount, 'addon_amount' => 0, 'quoted_addon_amount' => 0,
            'currency' => 'IDR', 'category' => ['en' => 'Registration', 'id' => 'Registrasi'],
            'journal_target' => 'regular', 'legacy' => true,
        ];
    }

    /** Jumlah yang benar-benar sudah masuk, dari baris pembayaran yang berhasil. */
    public function paidAmount(): float
    {
        return (float) $this->payments()->where('status', 'success')->sum('amount');
    }

    public function outstandingAmount(): float
    {
        return max(0, (float) $this->amount - $this->paidAmount());
    }

    /** Cicilan pertama untuk invoice ini, mengikuti pilihan jurnalnya. */
    public function firstInstallmentAmount(): float
    {
        $sinta3 = ($this->priceDetails()['journal_target'] ?? 'regular') === 'sinta3';

        return (float) $this->registrationFee?->firstInstallmentFor($sinta3);
    }

    /**
     * Nominal yang ditagihkan pada pembayaran berikutnya.
     *
     * Cicilan pertama memakai angka yang ditetapkan panitia; sesudah itu yang
     * ditagih selalu sisanya, jadi dua cicilan pasti berjumlah tepat total.
     */
    public function amountDueNow(): float
    {
        $first = $this->firstInstallmentAmount();

        // Panitia bisa mengosongkan nominal cicilan setelah author memilihnya;
        // kalau itu terjadi, yang ditagih kembali ke sisa penuh, bukan nol —
        // nol akan membuat tombol bayarnya menolak dengan 409.
        if ($this->installment_plan && $this->paidAmount() <= 0 && $first > 0) {
            return min($first, $this->outstandingAmount());
        }

        return $this->outstandingAmount();
    }

    /**
     * Pilihan cara membayar masih terbuka: belum ada yang dibayar, dan tidak
     * sedang dibebaskan voucher. Sengaja tidak memeriksa installment_plan —
     * selama belum ada uang masuk, author boleh berganti pikiran, dan
     * pilihannya baru mengunci saat pembayaran pertama diterima.
     */
    public function allowsInstallments(): bool
    {
        return $this->status === 'pending'
            && ! $this->isWaived()
            && $this->paidAmount() <= 0
            && (bool) $this->registrationFee?->allowsInstallments()
            && (float) $this->amount > $this->firstInstallmentAmount();
    }

    /** Sudah membayar sebagian, tapi belum lunas. */
    public function isPartiallyPaid(): bool
    {
        return $this->status !== 'paid' && $this->paidAmount() > 0;
    }

    public function installmentDueAt(): ?CarbonInterface
    {
        return app(ConferenceDeadlines::class)->date('installment', $this->edition_id);
    }

    /** Menunggak: cicilan pertama lunas, sisanya lewat tenggat pelunasan. */
    public function isInstallmentOverdue(): bool
    {
        $due = $this->installmentDueAt();

        return $this->installment_plan
            && $this->isPartiallyPaid()
            && $due !== null
            && now()->greaterThan($due);
    }

    /**
     * Ada pembayaran yang masih hidup, sehingga total tagihan tidak boleh
     * berubah — mengubahnya akan membuat halaman bayar yang sudah dibuka
     * menagih angka yang berbeda dari invoicenya.
     *
     * Order yang masa berlakunya di gateway sudah habis tidak ikut menahan:
     * kalau ikut, satu percobaan bayar yang ditinggalkan akan mengunci pilihan
     * jurnal dan voucher selamanya, tanpa penjelasan apa pun ke author.
     */
    public function hasUnresolvedPayment(): bool
    {
        return $this->payments()
            ->whereIn('status', ['initiated', 'success'])
            ->get()
            ->contains(fn (Payment $payment): bool => $payment->status === 'success' || ! $payment->hasExpired());
    }

    public function registerMediaCollections(): void
    {
        // Bukti transfer (khusus payment_method = manual).
        $this->addMediaCollection('payment_proof')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function redemption(): HasOne
    {
        return $this->hasOne(VoucherRedemption::class);
    }

    /** Lunas tanpa transaksi apa pun karena seluruh tagihan ditanggung voucher. */
    public function isWaived(): bool
    {
        return $this->payment_method === 'waived';
    }

    public function registrationFee(): BelongsTo
    {
        return $this->belongsTo(RegistrationFee::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
