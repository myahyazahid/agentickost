<?php

namespace App\Modules\Onboarding\Support;

use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\OpeningBalance;
use App\Modules\Finance\States\OpeningBalance\Posted;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomPrice;

/**
 * Which onboarding steps the tenant has done (FR-ONB-06), read from the
 * data itself so a step done outside the wizard also counts.
 */
final class OnboardingProgress
{
    public const PROPERTY = 'property';

    public const ROOMS = 'rooms';

    public const BANK_ACCOUNT = 'bank_account';

    public const CONTRACTS = 'contracts';

    public const OPENING_BALANCE = 'opening_balance';

    /**
     * @return array<string, bool> step => done, in the order to do them
     */
    public static function steps(): array
    {
        return [
            self::PROPERTY => Property::query()->exists(),
            self::ROOMS => Room::query()->exists() && RoomPrice::query()->exists(),
            self::BANK_ACCOUNT => BankAccount::query()->where('is_active', true)->exists(),
            self::CONTRACTS => Contract::query()->whereIn('status', ContractState::runningValues())->exists(),
            self::OPENING_BALANCE => OpeningBalance::query()->where('status', Posted::$name)->exists(),
        ];
    }

    public static function isComplete(): bool
    {
        return ! in_array(false, self::steps(), true);
    }
}
