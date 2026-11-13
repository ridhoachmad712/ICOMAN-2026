<?php

namespace App\Filament\Author\Pages;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;

class AuthorProfile extends EditProfile
{
    public static function getLabel(): string
    {
        return app()->getLocale() === 'id' ? 'Profil Saya' : 'My Profile';
    }

    public function form(Schema $schema): Schema
    {
        $id = app()->getLocale() === 'id';
        $locked = $this->getUser()->identityIsLocked();
        $lockedHint = $id
            ? 'Terkunci karena sertifikat Anda sudah terbit. Hubungi panitia bila ada yang perlu diperbaiki.'
            : 'Locked because your certificate has been issued. Contact the committee if anything needs correcting.';

        return $schema->components([
            Section::make($id ? 'Identitas & Jalur Partisipasi' : 'Identity & Participation Path')
                ->description($id ? 'Digunakan pada paper, invoice, dan komunikasi panitia.' : 'Used for papers, invoices, and committee communication.')
                ->columns(2)
                ->schema([
                    $this->getNameFormComponent()
                        ->disabled($locked)
                        ->helperText($locked ? $lockedHint : null),
                    $this->getEmailFormComponent(),
                    Select::make('participation_type')
                        ->label(app()->getLocale() === 'id' ? 'Jalur partisipasi' : 'Participation path')
                        ->options([
                            'presenter' => app()->getLocale() === 'id' ? 'Pemakalah / Presenter' : 'Presenter',
                            'participant' => app()->getLocale() === 'id' ? 'Peserta Seminar' : 'Seminar Participant',
                        ])
                        ->required(),
                    TextInput::make('affiliation')->label($id ? 'Afiliasi' : 'Affiliation')->maxLength(255)
                        ->disabled($locked)
                        ->helperText($locked ? $lockedHint : null),
                    TextInput::make('country')->label(app()->getLocale() === 'id' ? 'Negara' : 'Country')->maxLength(255),
                    TextInput::make('phone')->label(app()->getLocale() === 'id' ? 'Nomor WhatsApp' : 'WhatsApp Number')->maxLength(50),
                ]),
            Section::make(app()->getLocale() === 'id' ? 'Ubah Password' : 'Change Password')
                ->collapsed()
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),
        ]);
    }

    /**
     * Isian yang dinonaktifkan tidak ikut disimpan, tetapi kiriman Livewire bisa
     * direkayasa; data yang masuk ke penyimpanan tetap dibuang di sini.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->getUser()->identityIsLocked()) {
            unset($data['name'], $data['affiliation']);
        }

        return parent::mutateFormDataBeforeSave($data);
    }

    protected function beforeSave(): void
    {
        $user = $this->getUser();
        $requestedType = $this->data['participation_type'] ?? null;

        if ($user->participation_type
            && $requestedType !== $user->participation_type
            && ($user->submissions()->exists() || $user->registrations()->exists())) {
            $this->addError('data.participation_type', app()->getLocale() === 'id'
                ? 'Jalur tidak dapat diubah setelah paper atau registrasi dibuat. Hubungi panitia untuk koreksi.'
                : 'The path cannot be changed after creating a paper or registration. Contact the committee for corrections.');
            throw new Halt;
        }
    }
}
