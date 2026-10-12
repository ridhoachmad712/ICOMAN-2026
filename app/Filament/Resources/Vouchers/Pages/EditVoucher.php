<?php

namespace App\Filament\Resources\Vouchers\Pages;

use App\Filament\Resources\Vouchers\VoucherResource;
use App\Models\Voucher;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditVoucher extends EditRecord
{
    protected static string $resource = VoucherResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeSave(): void
    {
        // A redemption may arrive after the form was opened. Lock the same
        // voucher row used by VoucherRedeemer before checking the live quota.
        $voucher = Voucher::whereKey($this->record->id)->lockForUpdate()->firstOrFail();
        $used = $voucher->usedSlots();

        if ((int) ($this->data['quota'] ?? $voucher->quota) < max(1, $used)) {
            throw ValidationException::withMessages([
                'data.quota' => 'Kuota tidak boleh lebih kecil dari jumlah pengguna yang sudah memakai voucher.',
            ]);
        }

        if ($used > 0 && (
            (isset($this->data['code']) && Voucher::normalizeCode($this->data['code']) !== $voucher->code)
            || (isset($this->data['edition_id']) && (int) $this->data['edition_id'] !== $voucher->edition_id)
            || (isset($this->data['type']) && $this->data['type'] !== $voucher->type)
            || (isset($this->data['registrant_category']) && $this->data['registrant_category'] !== $voucher->registrant_category)
        )) {
            throw ValidationException::withMessages([
                'data.code' => 'Kode, jenis, edisi, dan kategori peserta voucher yang sudah dipakai tidak dapat diubah.',
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
