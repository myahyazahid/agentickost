<?php

namespace App\Modules\Finance\Filament\App\Resources\OpeningBalances\Schemas;

use App\Modules\Finance\Actions\RefundDeposit;
use App\Modules\Finance\Enums\OpeningBalanceKind;
use App\Modules\Finance\Models\OpeningBalance;
use App\Modules\Finance\Models\OpeningBalanceLine;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Support\Filament\MoneyInput;
use App\Support\Money\Rupiah;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * The opening balance form (FR-ONB-04): one list per kind of balance, stored
 * as lines. The totals at the bottom let the owner match them against the
 * old books before posting.
 */
final class OpeningBalanceForm
{
    /**
     * Form list => line kind.
     */
    private const LISTS = [
        'receivables' => OpeningBalanceKind::Receivable,
        'deposits' => OpeningBalanceKind::Deposit,
        'credits' => OpeningBalanceKind::Credit,
        'cash' => OpeningBalanceKind::Cash,
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    DatePicker::make('cutoff_date')
                        ->label('Tanggal cut-off')
                        ->helperText('Saldo per akhir hari ini, biasanya hari terakhir sebelum mulai memakai Agentic Kost. Tagihan sesudah tanggal ini dibuat Agentic Kost.')
                        ->maxDate(now())
                        ->required(),
                ]),
            self::contractList('receivables', 'Tunggakan penghuni', 'Sisa tagihan yang belum dibayar per tanggal cut-off. Menjadi tagihan saldo awal yang bisa dibayar seperti tagihan biasa.', 'Tambah tunggakan'),
            self::contractList('deposits', 'Deposit yang dipegang', 'Deposit penghuni yang masih Anda simpan. Hanya untuk kontrak yang diimpor.', 'Tambah deposit', importedOnly: true),
            self::contractList('credits', 'Saldo kredit penghuni', 'Kelebihan bayar yang akan memotong tagihan berikutnya.', 'Tambah saldo kredit'),
            Section::make('Kas dan rekening')
                ->description('Uang tunai dan saldo rekening per tanggal cut-off.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('cash')
                        ->hiddenLabel()
                        ->addActionLabel('Tambah saldo kas atau rekening')
                        ->defaultItems(0)
                        ->columns(['default' => 1, 'md' => 2])
                        ->live(onBlur: true)
                        ->schema([
                            Select::make('account_id')
                                ->label('Kas atau rekening')
                                ->options(fn (): array => RefundDeposit::payoutAccounts()->pluck('name', 'id')->all())
                                ->required(),
                            MoneyInput::make('amount')->label('Saldo')->required()->minValue(1),
                        ]),
                ]),
            Section::make('Ringkasan')
                ->columnSpanFull()
                ->schema([
                    Text::make(fn (Get $get): string => self::summary($get)),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{cutoff_date: mixed, lines: list<array<string, mixed>>}
     */
    public static function toInput(array $data): array
    {
        $lines = [];

        foreach (self::LISTS as $list => $kind) {
            foreach (array_values(is_array($data[$list] ?? null) ? $data[$list] : []) as $item) {
                $lines[] = [
                    'kind' => $kind->value,
                    'contract_id' => $kind->isPerContract() ? ($item['contract_id'] ?? null) : null,
                    'account_id' => $kind->isPerContract() ? null : ($item['account_id'] ?? null),
                    'amount' => $item['amount'] ?? null,
                    'note' => $item['note'] ?? null,
                ];
            }
        }

        return ['cutoff_date' => $data['cutoff_date'] ?? null, 'lines' => $lines];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromRecord(OpeningBalance $balance): array
    {
        $data = ['cutoff_date' => $balance->cutoff_date->toDateString()];

        foreach (self::LISTS as $list => $kind) {
            $data[$list] = array_values($balance->lines()->where('kind', $kind->value)->orderBy('id')->get()
                ->map(fn (OpeningBalanceLine $line): array => array_filter([
                    'contract_id' => $line->contract_id,
                    'account_id' => $line->account_id,
                    'amount' => $line->amount,
                    'note' => $line->note,
                ], fn (mixed $value): bool => $value !== null))
                ->all());
        }

        return $data;
    }

    /**
     * Runs the save or post Action from a page: a date error goes on the date
     * field, any other problem becomes a notification and stops the page.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            if (isset($errors['cutoff_date'])) {
                throw ValidationException::withMessages(['data.cutoff_date' => $errors['cutoff_date']]);
            }

            Notification::make()
                ->danger()
                ->title('Saldo awal belum bisa disimpan')
                ->body(implode(' ', Arr::flatten($errors)))
                ->send();

            throw new Halt;
        }
    }

    /**
     * Running contracts, labelled the way the owner knows them.
     *
     * @return array<string, string>
     */
    public static function contractOptions(bool $importedOnly = false): array
    {
        return Contract::query()
            ->whereIn('status', ContractState::runningValues())
            ->when($importedOnly, fn ($query) => $query->whereNotNull('imported_at'))
            ->with(['room', 'property', 'payer'])
            ->get()
            ->sortBy(fn (Contract $contract): string => ($contract->property->name ?? '').'|'.($contract->room->number ?? ''))
            ->mapWithKeys(fn (Contract $contract): array => [
                $contract->id => "{$contract->property?->name}, kamar {$contract->room?->number}, {$contract->payer?->name}",
            ])
            ->all();
    }

    private static function contractList(string $name, string $heading, string $description, string $addLabel, bool $importedOnly = false): Section
    {
        return Section::make($heading)
            ->description($description)
            ->columnSpanFull()
            ->schema([
                Repeater::make($name)
                    ->hiddenLabel()
                    ->addActionLabel($addLabel)
                    ->defaultItems(0)
                    ->columns(['default' => 1, 'md' => 3])
                    ->live(onBlur: true)
                    ->schema([
                        Select::make('contract_id')
                            ->label('Kontrak')
                            ->options(fn (): array => self::contractOptions($importedOnly))
                            ->searchable()
                            ->required(),
                        MoneyInput::make('amount')->label('Jumlah')->required()->minValue(1),
                        TextInput::make('note')->label('Catatan')->placeholder('Misal: sewa Agustus dan September')->maxLength(255),
                    ]),
            ]);
    }

    private static function summary(Get $get): string
    {
        $totals = [];

        foreach (array_keys(self::LISTS) as $list) {
            $items = $get($list);
            $totals[$list] = array_sum(array_map(
                fn (mixed $item): int => is_array($item) ? (int) preg_replace('/\D/', '', (string) ($item['amount'] ?? '')) : 0,
                is_array($items) ? $items : [],
            ));
        }

        $equity = $totals['cash'] + $totals['receivables'] - $totals['deposits'] - $totals['credits'];

        return sprintf(
            'Kas dan rekening %s, ditambah tunggakan %s, dikurangi deposit %s dan saldo kredit %s. Hasilnya, %s, dicatat sebagai ekuitas saldo awal.',
            Rupiah::format($totals['cash']),
            Rupiah::format($totals['receivables']),
            Rupiah::format($totals['deposits']),
            Rupiah::format($totals['credits']),
            Rupiah::format($equity),
        );
    }
}
