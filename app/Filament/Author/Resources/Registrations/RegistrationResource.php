<?php

namespace App\Filament\Author\Resources\Registrations;

use App\Filament\Author\Resources\Registrations\Pages\CreateRegistration;
use App\Filament\Author\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Author\Resources\Registrations\Pages\ViewRegistration;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $slug = 'registrations';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'id' ? 'Registrasi & Pembayaran' : 'Registration & Payment';
    }

    public static function getModelLabel(): string
    {
        return app()->getLocale() === 'id' ? 'registrasi' : 'registration';
    }

    // Seluruh alur author dijalankan dari Dashboard (satu tempat). Halaman ini
    // tetap bisa diakses via tautan dashboard, tapi tidak muncul sebagai menu.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $author = Filament::auth()->user();
        $edition = currentEdition();
        $audience = $author?->isPresenter() ? 'presenter' : 'participant';
        $submissions = $author?->submissions()
            ->where('edition_id', $edition?->id)
            ->where('status', 'accepted')
            ->whereNotNull('loa_issued_at')
            ->whereDoesntHave('registrations', fn (Builder $query) => $query->whereIn('status', ['pending', 'pending_verification', 'paid']))
            ->latest('submitted_at')
            ->get() ?? collect();
        $eligibleSubmission = $submissions->first();
        $sinta3Fee = (int) rescue(fn () => siteSettings()->sinta3_fee, 0, false);
        $fees = RegistrationFee::query()
            ->where('edition_id', $edition?->id)
            ->where('audience', $audience)
            ->where('registrant_category', $author?->feeCategory() ?? 'general')
            ->orderBy('order')
            ->get();

        // Harga per paket untuk perhitungan total reaktif di form.
        $feePrices = $fees->mapWithKeys(fn (RegistrationFee $fee) => [$fee->id => (float) $fee->currentPrice()])->all();
        $sinta3Offered = (bool) ($author?->isPresenter() && $eligibleSubmission?->sinta3_offered);

        return $schema->components([
            Section::make(app()->getLocale() === 'id' ? 'Pilih Registrasi' : 'Choose Registration')
                ->description($author?->isPresenter()
                    ? (app()->getLocale() === 'id'
                        ? 'Pembayaran tersedia setelah abstract Anda dinyatakan accepted. Selesaikan pembayaran untuk mengunci slot presentasi dan akses seminar.'
                        : 'Payment becomes available once your abstract is accepted. Complete payment to secure your presentation slot and seminar access.')
                    : (app()->getLocale() === 'id' ? 'Pilih paket peserta yang sesuai.' : 'Choose the attendee package that applies to you.'))
                ->schema([
                    Select::make('submission_id')
                        ->label(app()->getLocale() === 'id' ? 'Paper yang accepted' : 'Accepted paper')
                        ->options($submissions->mapWithKeys(fn (Submission $submission) => [$submission->id => $submission->submission_number.' — '.$submission->title]))
                        ->default(request()->integer('submission') ?: $submissions->first()?->id)
                        ->searchable()
                        ->required($author?->isPresenter())
                        ->visible($author?->isPresenter()),
                    Select::make('registration_fee_id')
                        ->label(app()->getLocale() === 'id' ? 'Paket registrasi' : 'Registration package')
                        ->options($fees->mapWithKeys(fn (RegistrationFee $fee) => [
                            $fee->id => $fee->category.' — '.$fee->currency.' '.number_format((float) $fee->currentPrice(), 0, ',', '.'),
                        ]))
                        ->helperText(app()->getLocale() === 'id' ? 'Harga yang ditampilkan sudah mengikuti periode early-bird atau reguler.' : 'The displayed price already reflects the early-bird or regular period.')
                        ->live()
                        ->required(),
                    Radio::make('journal_target')
                        ->label(app()->getLocale() === 'id' ? 'Target jurnal penerbitan' : 'Publication journal target')
                        ->options([
                            'regular' => app()->getLocale() === 'id' ? 'Jurnal Reguler — tanpa biaya tambahan' : 'Regular journal — no additional fee',
                            'sinta3' => (app()->getLocale() === 'id' ? 'Jurnal SINTA 3 — biaya tambahan Rp ' : 'SINTA 3 journal — additional IDR ').number_format((float) $sinta3Fee, 0, ',', '.'),
                        ])
                        ->descriptions([
                            'regular' => app()->getLocale() === 'id' ? 'Sudah termasuk dalam biaya registrasi.' : 'Included in the registration fee.',
                            'sinta3' => app()->getLocale() === 'id'
                                ? 'Menambah Rp '.number_format((float) $sinta3Fee, 0, ',', '.').' ke total pembayaran Anda.'
                                : 'Adds IDR '.number_format((float) $sinta3Fee, 0, ',', '.').' to your total payment.',
                        ])
                        ->default('regular')
                        ->required()
                        ->live()
                        ->helperText(app()->getLocale() === 'id'
                            ? '⚠️ Memilih Jurnal SINTA 3 menambah biaya penerbitan Rp '.number_format((float) $sinta3Fee, 0, ',', '.').'. Lihat total di bawah sebelum melanjutkan.'
                            : '⚠️ Choosing the SINTA 3 journal adds an IDR '.number_format((float) $sinta3Fee, 0, ',', '.').' publication fee. Check the total below before continuing.')
                        ->visible($sinta3Offered),
                    Placeholder::make('cost_summary')
                        ->label(app()->getLocale() === 'id' ? 'Rincian & total pembayaran' : 'Cost breakdown & total')
                        ->content(function (Get $get) use ($feePrices, $sinta3Fee, $sinta3Offered): HtmlString {
                            $id = app()->getLocale() === 'id';
                            $base = (float) ($feePrices[$get('registration_fee_id')] ?? 0);
                            $chooseSinta = $sinta3Offered && $get('journal_target') === 'sinta3';
                            $add = $chooseSinta ? (float) $sinta3Fee : 0.0;
                            $total = $base + $add;
                            $fmt = fn (float $v) => 'Rp '.number_format($v, 0, ',', '.');

                            $row = fn (string $label, string $value, string $style = '') => '<div style="display:flex;justify-content:space-between;gap:1rem;padding:.3rem 0;'.$style.'"><span>'.$label.'</span><span>'.$value.'</span></div>';

                            $html = $row($id ? 'Paket registrasi' : 'Registration package', $base > 0 ? $fmt($base) : '—');
                            if ($chooseSinta) {
                                $html .= $row($id ? 'Tambahan penerbitan Jurnal SINTA 3' : 'SINTA 3 journal publication add-on', '+ '.$fmt($add), 'color:#b45309;font-weight:600');
                            }
                            $html .= $row(
                                $id ? 'Total pembayaran' : 'Total to pay',
                                $fmt($total),
                                'border-top:1px solid #e5e7eb;margin-top:.35rem;padding-top:.6rem;font-weight:800;font-size:1.15rem;color:#111827'
                            );

                            return new HtmlString('<div style="max-width:26rem;border:1px solid #e5e7eb;border-radius:.6rem;padding:.85rem 1rem;background:#f9fafb">'.$html.'</div>');
                        }),
                ]),
            Section::make(app()->getLocale() === 'id' ? 'Metode Pembayaran' : 'Payment Method')
                ->description(app()->getLocale() === 'id' ? 'Metode masih dapat diubah selama pembayaran belum selesai.' : 'You can change this while the payment remains unpaid.')
                ->schema([
                    Radio::make('payment_method')
                        ->hiddenLabel()
                        ->options([
                            'manual' => app()->getLocale() === 'id' ? 'Transfer bank manual — unggah bukti transfer' : 'Manual bank transfer — upload payment proof',
                            'gateway' => app()->getLocale() === 'id' ? 'Pembayaran instan — Midtrans' : 'Instant payment — Midtrans',
                        ])
                        ->default('manual')
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Invoice')
                    ->formatStateUsing(fn (int $state) => '#'.str_pad((string) $state, 5, '0', STR_PAD_LEFT))
                    ->description(fn (Registration $record) => $record->created_at->format('d M Y')),
                TextColumn::make('registrationFee.category')
                    ->label(app()->getLocale() === 'id' ? 'Registrasi' : 'Registration')
                    ->description(fn (Registration $record) => $record->submission?->submission_number ?? (app()->getLocale() === 'id' ? 'Peserta seminar' : 'Seminar participant'))
                    ->wrap(),
                TextColumn::make('amount')->label(app()->getLocale() === 'id' ? 'Jumlah' : 'Amount')->money(fn (Registration $record) => $record->registrationFee?->currency ?: 'IDR'),
                TextColumn::make('payment_method')->label(app()->getLocale() === 'id' ? 'Metode' : 'Method')->formatStateUsing(fn (string $state) => $state === 'manual' ? 'Bank Transfer' : 'Midtrans')->visibleFrom('md'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => app()->getLocale() === 'id' ? match ($state) {
                    'pending' => 'Belum dibayar', 'pending_verification' => 'Menunggu verifikasi',
                    'paid' => 'Lunas', 'failed' => 'Gagal', default => ucwords(str_replace('_', ' ', $state)),
                } : ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state) => match ($state) {'paid'=>'success','failed'=>'danger','pending_verification'=>'warning',default=>'gray'}),
            ])
            ->recordUrl(fn (Registration $record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->emptyStateHeading(app()->getLocale() === 'id' ? 'Belum ada registrasi' : 'No registrations yet')
            ->emptyStateDescription(app()->getLocale() === 'id' ? 'Registrasi yang Anda buat akan tampil di sini.' : 'Registrations you create will appear here.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('author_id', Filament::auth()->id())
            ->with(['registrationFee', 'submission', 'payments']);
    }

    public static function canCreate(): bool
    {
        $author = Filament::auth()->user();
        $edition = currentEdition();

        if (! $author?->participation_type || ! $edition) {
            return false;
        }

        if ($author->isParticipant()) {
            return ! $author->registrations()->where('edition_id', $edition->id)->whereNull('submission_id')->whereIn('status', ['pending', 'pending_verification', 'paid'])->exists();
        }

        // Presenter: boleh membuat registrasi bila punya paper accepted yang
        // LOA-nya sudah diterbitkan dan belum memiliki registrasi aktif.
        return $author->submissions()
            ->where('edition_id', $edition->id)
            ->where('status', 'accepted')
            ->whereNotNull('loa_issued_at')
            ->whereDoesntHave('registrations', fn (Builder $query) => $query->whereIn('status', ['pending', 'pending_verification', 'paid']))
            ->exists();
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistrations::route('/'),
            'create' => CreateRegistration::route('/create'),
            'view' => ViewRegistration::route('/{record}'),
        ];
    }
}
