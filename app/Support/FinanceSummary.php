<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Builder;

/**
 * Angka-angka uang konferensi, dihitung di satu tempat.
 *
 * Ditaruh terpisah dari halaman yang menampilkannya karena angka yang sama
 * muncul di beberapa tempat sekaligus — ringkasan, rekap per kategori, dan
 * berkas export. Kalau tiap tempat menghitung sendiri, cepat atau lambat
 * ketiganya akan berbeda, dan yang membaca tidak punya cara tahu mana yang
 * benar.
 *
 * Dua kaidah yang dipegang seluruh berkas ini:
 *
 * 1. **Diterima** berarti ada baris pembayaran berhasil. Bukan status invoice.
 *    Invoice bertanda lunas tanpa baris pembayaran tidak menambah pemasukan,
 *    dan itu memang benar: statusnya bisa saja salah, uangnya tidak.
 * 2. **Piutang** hanya dihitung dari invoice yang masih mungkin dibayar.
 *    Invoice yang dibebaskan voucher atau sudah gagal bukan uang yang
 *    tertunda — itu uang yang tidak akan pernah datang, dan mencampurnya
 *    membuat piutang terlihat lebih besar daripada kenyataannya.
 */
class FinanceSummary
{
    public function __construct(private ?int $editionId = null)
    {
        $this->editionId ??= currentEdition()?->id;
    }

    /** Uang yang benar-benar sudah masuk. */
    public function received(): float
    {
        return (float) Payment::query()
            ->where('status', 'success')
            ->whereHas('registration', fn (Builder $q) => $this->scope($q))
            ->sum('amount');
    }

    /** Yang masih ditunggu dari invoice yang belum lunas. */
    public function outstanding(): float
    {
        return $this->billable()->get()->sum(fn (Registration $r): float => $r->outstandingAmount());
    }

    /** Nilai yang dibebaskan voucher: peserta terdaftar tanpa uang masuk. */
    public function waived(): float
    {
        return (float) $this->scope(Registration::query())
            ->where('payment_method', 'waived')
            ->sum('amount');
    }

    /** Invoice yang menunggu diperiksa panitia. */
    public function awaitingVerification(): int
    {
        return $this->scope(Registration::query())
            ->where('status', 'pending_verification')
            ->count();
    }

    /** Cicilan pertama lunas, sisanya lewat tenggat. */
    public function overdueInstallments(): int
    {
        return $this->billable()
            ->where('installment_plan', true)
            ->get()
            ->filter(fn (Registration $r): bool => $r->isInstallmentOverdue())
            ->count();
    }

    /**
     * Uang masuk menurut caranya, untuk dicocokkan dengan dua sumber berbeda:
     * gateway dengan laporan BorderPay, manual dengan mutasi rekening.
     *
     * @return array<string, float>
     */
    public function receivedByMethod(): array
    {
        return Payment::query()
            ->where('status', 'success')
            ->whereHas('registration', fn (Builder $q) => $this->scope($q))
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }

    /**
     * Invoice yang uangnya masih mungkin datang.
     *
     * Yang dibebaskan voucher dikeluarkan karena memang tidak akan membayar,
     * dan yang gagal karena sudah berhenti. Keduanya kalau ikut dihitung
     * membuat piutang tampak lebih besar daripada yang sebenarnya tertunda.
     */
    public function billable(): Builder
    {
        return $this->scope(Registration::query())
            ->whereNot('payment_method', 'waived')
            ->whereNotIn('status', ['failed'])
            ->with('payments');
    }

    /**
     * Invoice yang benar-benar masih bersisa.
     *
     * Sisanya dihitung di SQL, bukan setelah baris diambil, supaya daftar
     * piutang bisa memakainya sebagai query — daftar berjudul "Piutang" yang
     * memuat baris bersisa nol membantah judulnya sendiri.
     */
    public function stillOwing(): Builder
    {
        return $this->billable()->whereRaw(
            'registrations.amount > (select coalesce(sum(payments.amount), 0) from payments'
            .' where payments.registration_id = registrations.id and payments.status = ?)',
            ['success'],
        );
    }

    private function scope(Builder $query): Builder
    {
        return $this->editionId
            ? $query->where('edition_id', $this->editionId)
            : $query->whereRaw('1 = 0');
    }
}
