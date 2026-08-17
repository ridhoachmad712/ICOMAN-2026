<?php

namespace App\Filament\Concerns;

/**
 * Dipakai di Edit page resource yang modelnya memakai Spatie HasTranslations.
 * Mengubah nilai kolom translatable (JSON) menjadi array per-locale
 * sehingga field form bernama `field.en` / `field.id` terisi benar saat load.
 * Saat simpan, Spatie otomatis menyimpan array translasi (tidak perlu hook tambahan).
 */
trait ExpandsTranslationsOnFill
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if (method_exists($record, 'getTranslatableAttributes')) {
            foreach ($record->getTranslatableAttributes() as $attribute) {
                $data[$attribute] = $record->getTranslations($attribute);
            }
        }

        return $data;
    }
}
