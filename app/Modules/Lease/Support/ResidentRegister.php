<?php

namespace App\Modules\Lease\Support;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractResident;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Property\Models\Property;

/**
 * Residents living in a property now, as neighbourhood heads (RT/RW) ask
 * for them (FR-PNH-07). Identity numbers are included only on request, by
 * an Action that records the export.
 */
final class ResidentRegister
{
    /**
     * @return list<array{no: int, name: string, gender: ?string, birth_date: ?string, identity: ?string, phone: string, institution: ?string, vehicle: ?string, room: ?string, since: ?string}>
     */
    public static function rows(Property $property, bool $withIdentity = false): array
    {
        $stays = ContractResident::query()
            ->whereNull('left_on')
            ->whereHas('contract', fn ($query) => $query
                ->where('property_id', $property->id)
                ->whereIn('status', ContractState::runningValues()))
            ->with(['contract.room', 'resident'])
            ->get()
            ->sortBy(fn (ContractResident $stay): string => (string) $stay->contract?->room?->number, SORT_NATURAL);

        $rows = [];

        foreach ($stays as $stay) {
            $resident = $stay->resident;
            $contract = $stay->contract;

            if (! $resident instanceof Resident || ! $contract instanceof Contract) {
                continue;
            }

            $rows[] = [
                'no' => count($rows) + 1,
                'name' => $resident->full_name,
                'gender' => $resident->gender?->getLabel(),
                'birth_date' => $resident->birth_date?->translatedFormat('j F Y'),
                'identity' => $withIdentity ? self::identity($resident) : $resident->identity_type?->getLabel(),
                'phone' => $resident->phone,
                'institution' => $resident->institution,
                'vehicle' => $resident->vehicle_plate,
                'room' => $contract->room?->number,
                'since' => $stay->joined_on->translatedFormat('j F Y'),
            ];
        }

        return $rows;
    }

    private static function identity(Resident $resident): ?string
    {
        if ($resident->identity_number === null) {
            return $resident->identity_type?->getLabel();
        }

        return trim(($resident->identity_type?->getLabel() ?? 'Identitas').' '.$resident->identity_number);
    }
}
