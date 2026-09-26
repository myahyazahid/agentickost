<?php

namespace App\Modules\Property\Actions;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Property\Enums\GenderPolicy;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Timezone;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final class CreateProperty extends Action
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AttachmentSync $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): Property
    {
        $this->authorize('create', Property::class);

        $data = $this->validate($input, self::rules($this->tenants->id()));

        return $this->transaction(function () use ($data): Property {
            $property = Property::create(Arr::except($data, 'photos'));

            $property->resolvedSettings();
            $this->attachments->sync($property, AttachmentCollection::Photo, $data['photos'] ?? []);

            return $property;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $tenantId, ?Property $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('properties', 'code')->where('tenant_id', $tenantId)->ignore($ignore?->id),
            ],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:80'],
            'province' => ['required', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'timezone' => ['required', Rule::enum(Timezone::class)],
            'gender_policy' => ['required', Rule::enum(GenderPolicy::class)],
            'rules' => ['nullable', 'string'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', 'max:80'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['string'],
        ];
    }
}
