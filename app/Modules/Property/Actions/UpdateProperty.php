<?php

namespace App\Modules\Property\Actions;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use Illuminate\Support\Arr;

final class UpdateProperty extends Action
{
    public function __construct(private readonly AttachmentSync $attachments) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Property $property, array $input): Property
    {
        $this->authorize('update', $property);

        $data = $this->validate($input, CreateProperty::rules($property->tenant_id, $property));

        return $this->transaction(function () use ($property, $data): Property {
            $property->update(Arr::except($data, 'photos'));

            if (array_key_exists('photos', $data)) {
                $this->attachments->sync($property, AttachmentCollection::Photo, $data['photos'] ?? []);
            }

            return $property;
        });
    }
}
