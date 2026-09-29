<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Enums\PlanFeature;
use App\Modules\Subscription\Models\Plan;
use App\Support\Actions\Action;
use Illuminate\Validation\Rule;

/**
 * Creates or changes a plan (FR-SUB-01, FR-SUB-02). A price change applies
 * to invoices issued afterwards; invoices already issued keep their amount.
 */
final class SavePlan extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?Plan $plan = null): Plan
    {
        $plan === null
            ? $this->authorize('create', Plan::class)
            : $this->authorize('update', $plan);

        $data = $this->validate($input, [
            'code' => ['required', 'string', 'max:40', 'alpha_dash:ascii', Rule::unique('plans', 'code')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'monthly_price_amount' => ['required', 'integer', 'min:0'],
            'yearly_price_amount' => ['nullable', 'integer', 'min:0'],
            'max_rooms' => ['nullable', 'integer', 'min:1'],
            'max_properties' => ['nullable', 'integer', 'min:1'],
            'max_staff' => ['nullable', 'integer', 'min:1'],
            'monthly_message_quota' => ['nullable', 'integer', 'min:0'],
            'monthly_ai_credit_quota' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::enum(PlanFeature::class)],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $data['features'] = array_values(array_unique($data['features'] ?? []));
        $data['sort_order'] ??= 0;

        return $this->transaction(function () use ($plan, $data): Plan {
            $plan ??= new Plan;
            $plan->fill($data)->save();

            return $plan;
        });
    }
}
