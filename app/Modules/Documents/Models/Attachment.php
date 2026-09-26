<?php

namespace App\Modules\Documents\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Documents\Database\Factories\AttachmentFactory;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Modules\Tenancy\Support\TenantStorage;
use App\Support\Actors\ActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;

/**
 * A stored file. Always under tenants/{tenant_id}/ (NFR-ISO-03).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $attachable_type
 * @property string $attachable_id
 * @property AttachmentCollection $collection
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property bool $is_encrypted
 * @property ActorType $uploaded_by_type
 * @property string|null $uploaded_by_id
 */
#[Fillable([
    'attachable_type', 'attachable_id', 'collection', 'disk', 'path', 'original_name',
    'mime_type', 'size_bytes', 'is_encrypted', 'uploaded_by_type', 'uploaded_by_id',
])]
#[UseFactory(AttachmentFactory::class)]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A short-lived link to the file. Encrypted files go through the app so
     * they can be decrypted; the others come straight from the disk.
     */
    public function temporaryUrl(int $minutes = 5): string
    {
        if ($this->is_encrypted) {
            return URL::temporarySignedRoute('attachments.show', now()->addMinutes($minutes), ['attachment' => $this->id]);
        }

        return app(TenantStorage::class)->temporaryUrl($this->path, $minutes);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collection' => AttachmentCollection::class,
            'is_encrypted' => 'boolean',
            'size_bytes' => 'integer',
            'uploaded_by_type' => ActorType::class,
        ];
    }
}
