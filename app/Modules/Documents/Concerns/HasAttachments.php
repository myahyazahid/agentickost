<?php

namespace App\Modules\Documents\Concerns;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Paths of the files in one collection, oldest first.
     *
     * @return list<string>
     */
    public function attachmentPaths(AttachmentCollection $collection): array
    {
        return array_values($this->attachments()
            ->where('collection', $collection->value)
            ->oldest('id')
            ->pluck('path')
            ->all());
    }
}
