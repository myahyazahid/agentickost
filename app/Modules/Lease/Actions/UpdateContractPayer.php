<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Enums\PayerRelation;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Payer;
use App\Support\Actions\Action;

/**
 * Changes who is billed and who receives invoice notifications
 * (FR-PNH-03, FR-PNH-04). Invoices already issued keep their payer.
 */
final class UpdateContractPayer extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): Contract
    {
        $this->authorize('update', $contract);

        $rules = CreateContract::rules($contract->tenant_id);

        $data = $this->validate(CreateContract::normalized($input), [
            ...array_intersect_key($rules, array_flip(['payer', 'payer_name', 'payer_phone', 'payer_email', 'payer_relation'])),
            'notify_resident' => ['boolean'],
            'notify_payer' => ['boolean'],
        ]);

        return $this->transaction(function () use ($contract, $data): Contract {
            $primary = $contract->primaryResident();

            $payer = $data['payer'] === 'other'
                ? Payer::create([
                    'name' => $data['payer_name'],
                    'phone' => $data['payer_phone'],
                    'email' => $data['payer_email'] ?? null,
                    'relation' => $data['payer_relation'],
                ])
                : Payer::query()->firstOrCreate(
                    ['resident_id' => $primary?->id, 'relation' => PayerRelation::Self->value],
                    ['name' => $primary?->full_name, 'phone' => $primary?->phone, 'email' => $primary?->email],
                );

            $contract->update([
                'payer_id' => $payer->id,
                'notify_resident' => $data['notify_resident'] ?? $contract->notify_resident,
                'notify_payer' => $data['notify_payer'] ?? $contract->notify_payer,
            ]);

            return $contract;
        });
    }
}
