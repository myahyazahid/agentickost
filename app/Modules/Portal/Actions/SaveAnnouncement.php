<?php

namespace App\Modules\Portal\Actions;

use App\Modules\Portal\Models\Announcement;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use Illuminate\Validation\Rule;

/**
 * Writes or edits an announcement for one property. Published ones show in
 * the portal of the residents who live there (FR-PRT-05); a draft stays
 * with staff.
 */
final class SaveAnnouncement extends Action
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $input  property_id, title, body, publish
     */
    public function handle(array $input, ?Announcement $announcement = null): Announcement
    {
        $data = $this->validate($input, [
            'property_id' => ['required', Rule::exists('properties', 'id')->where('tenant_id', $this->tenants->id())->whereNull('deleted_at')],
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'publish' => ['boolean'],
        ]);

        $property = Property::query()->whereKey($data['property_id'])->firstOrFail();

        $announcement === null
            ? $this->authorize('createIn', [Announcement::class, $property])
            : $this->authorize('update', $announcement);

        return $this->transaction(function () use ($announcement, $data): Announcement {
            $actor = $this->actors->current();
            $announcement ??= new Announcement(['created_by' => $actor->type === ActorType::User ? $actor->id : $actor->user?->getAuthIdentifier()]);

            $announcement->property_id = $data['property_id'];
            $announcement->title = $data['title'];
            $announcement->body = $data['body'];

            if (($data['publish'] ?? false) && $announcement->published_at === null) {
                $announcement->published_at = now();
            } elseif (! ($data['publish'] ?? false)) {
                $announcement->published_at = null;
            }

            $announcement->save();

            return $announcement;
        });
    }
}
