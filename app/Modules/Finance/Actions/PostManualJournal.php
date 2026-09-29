<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Journal\JournalLineDraft;
use App\Modules\Finance\Journal\JournalPoster;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A journal entered by hand (FR-ACC-05), for what no business event posts:
 * depreciation, owner drawings, a bank fee, a correction between expense
 * accounts. Accounts kept in step with invoices, payments, deposits, and
 * staff cash are refused, so their sub-ledgers stay right.
 */
final class PostManualJournal extends Action
{
    /**
     * @var list<AccountSubtype>
     */
    public const RESTRICTED_SUBTYPES = [
        AccountSubtype::Receivable,
        AccountSubtype::DepositLiability,
        AccountSubtype::CreditLiability,
        AccountSubtype::AdvanceLiability,
        AccountSubtype::StaffCash,
    ];

    public function __construct(
        private readonly JournalPoster $poster,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  array<string, mixed>  $input  entry_date, description, property_id, lines: list of account_id, debit, credit, memo
     */
    public function handle(array $input): JournalEntry
    {
        $this->authorize('createManual', JournalEntry::class);

        $tenant = $this->tenants->tenant();
        $today = CarbonImmutable::now($tenant->default_timezone)->toDateString();

        $data = $this->validate($input, [
            'entry_date' => ['required', 'date', 'before_or_equal:'.$today],
            'description' => ['required', 'string', 'max:255'],
            'property_id' => ['nullable', Rule::exists('properties', 'id')->where('tenant_id', $tenant->id)->whereNull('deleted_at')],
            'lines' => ['required', 'array', 'min:2', 'max:50'],
            'lines.*.account_id' => ['required', 'distinct', Rule::exists('accounts', 'id')->where('tenant_id', $tenant->id)->where('is_active', true)],
            'lines.*.debit' => ['nullable', 'integer', 'min:0'],
            'lines.*.credit' => ['nullable', 'integer', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $drafts = self::drafts(array_values($data['lines']), $data['property_id'] ?? null);
        $property = isset($data['property_id']) ? Property::query()->whereKey($data['property_id'])->firstOrFail() : null;

        return $this->transaction(fn (): JournalEntry => $this->poster->post(
            JournalEvent::Manual,
            CarbonImmutable::parse($data['entry_date']),
            $data['description'],
            null,
            $property,
            $drafts,
        ) ?? throw ValidationException::withMessages(['lines' => 'Jurnal kosong: debit dan kredit saling meniadakan.']));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<JournalLineDraft>
     */
    private static function drafts(array $lines, ?string $propertyId): array
    {
        $accounts = Account::query()->whereIn('id', array_column($lines, 'account_id'))->get()->keyBy('id');
        $drafts = [];
        $debits = 0;
        $credits = 0;

        foreach ($lines as $index => $line) {
            $debit = (int) ($line['debit'] ?? 0);
            $credit = (int) ($line['credit'] ?? 0);
            $account = $accounts->get($line['account_id']);

            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages(["lines.{$index}.debit" => 'Isi debit atau kredit saja, salah satu.']);
            }

            if ($account instanceof Account && in_array($account->subtype, self::RESTRICTED_SUBTYPES, true)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => "Akun {$account->name} berubah lewat tagihan, pembayaran, deposit, atau setoran staf, bukan jurnal manual.",
                ]);
            }

            $debits += $debit;
            $credits += $credit;
            $memo = isset($line['memo']) && is_string($line['memo']) && $line['memo'] !== '' ? $line['memo'] : null;
            $drafts[] = new JournalLineDraft((string) $line['account_id'], $debit - $credit, null, $propertyId, $memo);
        }

        if ($debits !== $credits) {
            throw ValidationException::withMessages(['lines' => 'Total debit dan kredit harus sama. Selisihnya '.number_format(abs($debits - $credits), 0, ',', '.').' rupiah.']);
        }

        return $drafts;
    }
}
