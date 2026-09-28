<?php

namespace App\Modules\Onboarding\Filament\App\Widgets;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Filament\App\Resources\BankAccounts\BankAccountResource;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\OpeningBalanceResource;
use App\Modules\Onboarding\Enums\OnboardingPermission;
use App\Modules\Onboarding\Filament\App\Pages\ImportData;
use App\Modules\Onboarding\Filament\App\Pages\SetUpProperty;
use App\Modules\Onboarding\Support\OnboardingProgress;
use App\Modules\Property\Filament\App\Resources\RoomTypes\RoomTypeResource;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Onboarding steps still to do, on the owner's dashboard (FR-ONB-06). It
 * disappears once every step is done.
 */
class OnboardingChecklist extends TableWidget
{
    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return User::current()->can(OnboardingPermission::Manage->value) && ! OnboardingProgress::isComplete();
    }

    public function table(Table $table): Table
    {
        $steps = OnboardingProgress::steps();
        $done = count(array_filter($steps));

        return $table
            ->heading('Persiapan KostPilot')
            ->description(sprintf('%d dari %d langkah selesai. Setelah semuanya selesai, tagihan dan laporan berjalan dari data Anda sendiri.', $done, count($steps)))
            ->records(fn (): array => self::rows($steps))
            ->columns([
                IconColumn::make('done')
                    ->label('Selesai')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->falseColor('gray'),
                TextColumn::make('title')
                    ->label('Langkah')
                    ->weight('medium')
                    ->description(fn (array $record): string => $record['description'])
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(fn (array $record): string => $record['done'] ? 'Lihat' : 'Kerjakan')
                    ->color(fn (array $record): string => $record['done'] ? 'gray' : 'primary')
                    ->url(fn (array $record): string => $record['url']),
            ])
            ->paginated(false);
    }

    /**
     * @param  array<string, bool>  $steps
     * @return array<string, array{done: bool, title: string, description: string, url: string}>
     */
    private static function rows(array $steps): array
    {
        $details = [
            OnboardingProgress::PROPERTY => [
                'Siapkan properti',
                'Nama, alamat, aturan tagihan, dan denda lewat wizard.',
                SetUpProperty::getUrl(),
            ],
            OnboardingProgress::ROOMS => [
                'Masukkan tipe kamar, harga, dan kamar',
                'Wizard mengisi sebagian; sisanya lewat menu Tipe kamar atau Impor data.',
                RoomTypeResource::getUrl('index'),
            ],
            OnboardingProgress::BANK_ACCOUNT => [
                'Tambahkan rekening tujuan transfer',
                'Ditampilkan di setiap tagihan agar penghuni tahu ke mana membayar.',
                BankAccountResource::getUrl('index'),
            ],
            OnboardingProgress::CONTRACTS => [
                'Impor penghuni dan kontrak yang sedang berjalan',
                'Pakai template Excel. Tagihan berikutnya dibuat otomatis dari kontrak ini.',
                ImportData::getUrl(),
            ],
            OnboardingProgress::OPENING_BALANCE => [
                'Posting saldo awal',
                'Tunggakan, deposit yang dipegang, dan saldo kas per tanggal mulai memakai KostPilot.',
                OpeningBalanceResource::getUrl('index'),
            ],
        ];

        $rows = [];

        foreach ($steps as $step => $done) {
            [$title, $description, $url] = $details[$step];
            $rows[$step] = ['done' => $done, 'title' => $title, 'description' => $description, 'url' => $url];
        }

        return $rows;
    }
}
