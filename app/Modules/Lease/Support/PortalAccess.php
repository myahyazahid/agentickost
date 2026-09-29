<?php

namespace App\Modules\Lease\Support;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractResident;
use App\Modules\Lease\Models\Payer;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\States\Contract\Draft;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a portal login may see, decided by its phone number inside the
 * current tenant (FR-PRT-02, FR-PRT-07).
 *
 * - A resident sees the contracts they still live under (not the ones they
 *   moved out of), and the contracts they pay for.
 * - A payer who lives elsewhere sees only the contracts they pay for, and
 *   cannot report repairs or read announcements.
 *
 * Draft contracts are never shown. Anonymized people cannot log in.
 */
final class PortalAccess
{
    /**
     * @param  list<string>  $residentIds  every resident record with this phone
     * @param  list<string>  $livingContractIds
     * @param  list<string>  $payingContractIds
     */
    private function __construct(
        public readonly bool $isResident,
        private readonly array $residentIds,
        private readonly array $livingContractIds,
        private readonly array $payingContractIds,
    ) {}

    public static function for(Resident|Payer $account): self
    {
        $residentIds = self::lookUpResidentIds($account->phone);
        $isResident = $account instanceof Resident;

        return new self(
            $isResident,
            $isResident ? $residentIds : [],
            $isResident ? self::lookUpLivingContractIds($residentIds) : [],
            self::lookUpPayingContractIds($account->phone, $isResident ? $residentIds : []),
        );
    }

    /**
     * The account a phone number logs in as: a resident still living under
     * a contract, else a payer of one. Null when the number has nothing to
     * see.
     */
    public static function findAccount(string $phone): Resident|Payer|null
    {
        $residentIds = self::lookUpResidentIds($phone);
        $living = self::lookUpLivingContractIds($residentIds);

        if ($living !== []) {
            $stay = ContractResident::query()
                ->whereIn('resident_id', $residentIds)
                ->whereIn('contract_id', $living)
                ->whereNull('left_on')
                ->orderByDesc('joined_on')
                ->first();

            $resident = $stay === null ? null : Resident::query()->find($stay->resident_id);

            if ($resident instanceof Resident) {
                return $resident;
            }
        }

        if (self::lookUpPayingContractIds($phone, []) === []) {
            return null;
        }

        return Payer::query()
            ->where('phone', $phone)
            ->whereNull('anonymized_at')
            ->whereHas('contracts', fn (Builder $query) => $query->where('status', '!=', Draft::$name))
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return list<string>
     */
    public function contractIds(): array
    {
        return array_values(array_unique([...$this->livingContractIds, ...$this->payingContractIds]));
    }

    /**
     * @return list<string>
     */
    public function livingContractIds(): array
    {
        return $this->livingContractIds;
    }

    /**
     * @return list<string>
     */
    public function residentRecordIds(): array
    {
        return $this->residentIds;
    }

    public function canSee(Contract|string $contract): bool
    {
        return in_array($contract instanceof Contract ? $contract->id : $contract, $this->contractIds(), true);
    }

    public function livesIn(Contract|string $contract): bool
    {
        return in_array($contract instanceof Contract ? $contract->id : $contract, $this->livingContractIds, true);
    }

    /**
     * @return Builder<Contract>
     */
    public function contracts(): Builder
    {
        return Contract::query()->whereIn('id', $this->contractIds());
    }

    /**
     * @return list<string>
     */
    private static function lookUpResidentIds(string $phone): array
    {
        return array_values(Resident::query()
            ->where('phone', $phone)
            ->whereNull('anonymized_at')
            ->pluck('id')
            ->all());
    }

    /**
     * @param  list<string>  $residentIds
     * @return list<string>
     */
    private static function lookUpLivingContractIds(array $residentIds): array
    {
        if ($residentIds === []) {
            return [];
        }

        return array_values(ContractResident::query()
            ->whereIn('resident_id', $residentIds)
            ->whereNull('left_on')
            ->whereHas('contract', fn (Builder $query) => $query->where('status', '!=', Draft::$name))
            ->distinct()
            ->pluck('contract_id')
            ->all());
    }

    /**
     * @param  list<string>  $residentIds
     * @return list<string>
     */
    private static function lookUpPayingContractIds(string $phone, array $residentIds): array
    {
        $payers = Payer::query()
            ->whereNull('anonymized_at')
            ->where(fn (Builder $query) => $query
                ->where('phone', $phone)
                ->when($residentIds !== [], fn (Builder $query) => $query->orWhereIn('resident_id', $residentIds)))
            ->select('id');

        return array_values(Contract::query()
            ->whereIn('payer_id', $payers)
            ->where('status', '!=', Draft::$name)
            ->pluck('id')
            ->all());
    }
}
