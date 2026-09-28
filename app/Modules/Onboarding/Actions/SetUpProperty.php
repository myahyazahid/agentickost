<?php

namespace App\Modules\Onboarding\Actions;

use App\Modules\Finance\Actions\CreateBankAccount;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Property\Actions\CreateProperty;
use App\Modules\Property\Actions\CreateRoom;
use App\Modules\Property\Actions\CreateRoomType;
use App\Modules\Property\Actions\SetRoomPrice;
use App\Modules\Property\Actions\UpdatePropertySettings;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * The setup wizard (FR-ONB-01): a property with its billing and penalty
 * rules, room types with their price, the rooms of each type, and the
 * accounts residents transfer to, saved together or not at all. Each part
 * goes through the Action that owns it; errors come back under the wizard
 * field that caused them.
 */
final class SetUpProperty extends Action
{
    private const SETTINGS = [
        'billing_mode', 'fixed_billing_day', 'invoice_lead_days', 'proration_basis', 'rounding_unit',
        'grace_days', 'penalty_type', 'penalty_amount', 'penalty_percent', 'penalty_max_amount',
    ];

    public function __construct(
        private readonly CreateProperty $createProperty,
        private readonly UpdatePropertySettings $updateSettings,
        private readonly CreateRoomType $createRoomType,
        private readonly SetRoomPrice $setPrice,
        private readonly CreateRoom $createRoom,
        private readonly CreateBankAccount $createBankAccount,
    ) {}

    /**
     * @param  array<string, mixed>  $input  property fields, settings fields, room_types, bank_accounts
     */
    public function handle(array $input): Property
    {
        $this->authorize('create', Property::class);

        $data = $this->validate($input, [
            'room_types' => ['required', 'array', 'min:1'],
            'room_types.*.name' => ['required', 'string'],
            'room_types.*.default_capacity' => ['nullable', 'integer'],
            'room_types.*.description' => ['nullable', 'string'],
            'room_types.*.rental_period' => ['required', 'string'],
            'room_types.*.price' => ['required'],
            'room_types.*.room_numbers' => ['required', 'array', 'min:1'],
            'room_types.*.room_numbers.*' => ['string', 'max:20'],
            'bank_accounts' => ['nullable', 'array'],
        ]);

        self::ensureRoomNumbersUnique($data['room_types']);

        return $this->transaction(function () use ($input, $data): Property {
            $property = $this->createProperty->handle(Arr::except($input, [...self::SETTINGS, 'room_types', 'bank_accounts']));

            $settings = $property->resolvedSettings();
            $this->updateSettings->handle($property, [
                'allocation_order' => $settings->allocation_order,
                'notice_days' => $settings->notice_days,
                ...Arr::only($input, self::SETTINGS),
            ]);

            foreach (array_values($data['room_types']) as $index => $type) {
                $this->roomType($property, $type, "room_types.{$index}");
            }

            $hasDefault = BankAccount::query()->where('is_default', true)->exists();

            foreach (array_values($data['bank_accounts'] ?? []) as $index => $account) {
                self::prefixed("bank_accounts.{$index}", fn () => $this->createBankAccount->handle([
                    ...Arr::only(is_array($account) ? $account : [], ['kind', 'provider_name', 'account_number', 'account_holder']),
                    'is_default' => ! $hasDefault && $index === 0,
                ]));
            }

            return $property;
        });
    }

    /**
     * @param  array<string, mixed>  $type
     */
    private function roomType(Property $property, array $type, string $path): void
    {
        $roomType = self::prefixed($path, fn () => $this->createRoomType->handle($property, [
            'name' => $type['name'],
            'default_capacity' => $type['default_capacity'] ?? 1,
            'description' => $type['description'] ?? null,
        ]));

        self::prefixed($path, fn () => $this->setPrice->handle($roomType, [
            'rental_period' => $type['rental_period'],
            'amount' => $type['price'],
            'effective_from' => $property->today()->toDateString(),
        ]), ['amount' => 'price']);

        foreach ($type['room_numbers'] as $number) {
            self::prefixed($path, fn () => $this->createRoom->handle($property, [
                'room_type_id' => $roomType->id,
                'number' => trim((string) $number),
            ]), ['number' => 'room_numbers']);
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $types
     */
    private static function ensureRoomNumbersUnique(array $types): void
    {
        $seen = [];

        foreach (array_values($types) as $index => $type) {
            foreach ($type['room_numbers'] as $number) {
                $key = mb_strtolower(trim((string) $number));

                if (isset($seen[$key])) {
                    throw ValidationException::withMessages([
                        "room_types.{$index}.room_numbers" => "Nomor kamar {$number} dipakai dua kali.",
                    ]);
                }

                $seen[$key] = true;
            }
        }
    }

    /**
     * Runs a nested Action and moves its errors under the wizard path,
     * renaming fields whose wizard name differs.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @param  array<string, string>  $renames
     * @return TReturn
     */
    private static function prefixed(string $path, Closure $callback, array $renames = []): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => ["{$path}.".($renames[$key] ?? $key) => $messages])
                ->all());
        }
    }
}
