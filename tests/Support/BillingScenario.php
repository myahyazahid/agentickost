<?php

namespace Tests\Support;

use App\Modules\Billing\Actions\IssueDueInvoices;
use App\Modules\Billing\Actions\RecordMeterReading;
use App\Modules\Billing\Actions\SetUtilityRate;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\MeterReading;
use App\Modules\Billing\Models\UtilityRate;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Lease\Models\Contract;
use App\Modules\Property\Actions\UpdatePropertySettings;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Tenancy\Support\TenantStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builders for billing tests. Call inside a tenant context with an acting
 * owner (see loginAs()). Tests that record readings call Storage::fake().
 */
final class BillingScenario
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function settings(Property $property, array $overrides): void
    {
        app(UpdatePropertySettings::class)->handle($property, [
            ...$property->resolvedSettings()->attributesToArray(),
            ...$overrides,
        ]);
    }

    public static function meteredElectricity(Property $property, int $rate = 1_500, string $from = '2026-01-01'): UtilityRate
    {
        return app(SetUtilityRate::class)->handle($property, [
            'utility' => 'electricity',
            'mode' => 'metered',
            'unit' => 'kwh',
            'rate_amount' => $rate,
            'effective_from' => $from,
        ]);
    }

    public static function flatWater(Property $property, int $rate = 50_000, string $from = '2026-01-01'): UtilityRate
    {
        return app(SetUtilityRate::class)->handle($property, [
            'utility' => 'water',
            'mode' => 'flat',
            'rate_amount' => $rate,
            'effective_from' => $from,
        ]);
    }

    /**
     * A meter photo where Filament's upload field would put it.
     */
    public static function photo(): string
    {
        $path = app(TenantStorage::class)->path(AttachmentCollection::Meter->directory().'/'.Str::ulid().'.jpg');
        Storage::put($path, 'jpeg-bytes');

        return $path;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function reading(Room $room, string $date, int|string $value, array $overrides = []): MeterReading
    {
        return app(RecordMeterReading::class)->handle($room, [
            'utility' => 'electricity',
            'reading_date' => $date,
            'current_value' => $value,
            'photos' => [self::photo()],
            ...$overrides,
        ]);
    }

    /**
     * @return list<Invoice>
     */
    public static function issueDue(Contract $contract): array
    {
        return app(IssueDueInvoices::class)->handle($contract->refresh());
    }

    /**
     * Item lines of an invoice as [type, amount] pairs, in order.
     *
     * @return list<array{0: string, 1: int}>
     */
    public static function lines(Invoice $invoice): array
    {
        return array_values($invoice->items()->get()->map(fn ($item): array => [$item->type->value, $item->amount])->all());
    }
}
