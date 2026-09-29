<?php

namespace App\Modules\Documents\Support;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Tenancy\Support\TenantStorage;
use App\Support\Actors\ActorContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Records uploaded files as attachments of a model. Called from Actions, so
 * the rows are written in the Action's transaction.
 */
final class AttachmentSync
{
    public function __construct(
        private readonly TenantStorage $storage,
        private readonly ActorContext $actors,
    ) {}

    /**
     * Make the collection hold exactly the given paths. New paths are recorded,
     * paths no longer listed are removed together with their files once the
     * transaction commits.
     *
     * Paths come from form state, which the browser controls. A path this
     * owner does not have yet must be a fresh upload: inside the tenant's
     * folder for this collection, on disk, and not already the file of any
     * other record. Otherwise one record could take over, encrypt, or delete
     * another record's file.
     *
     * @param  list<string>  $paths  Files already uploaded under the tenant directory
     */
    public function sync(Model $owner, AttachmentCollection $collection, array $paths, string $field = 'photos'): void
    {
        $paths = array_values(array_unique($paths));

        $existing = Attachment::query()
            ->whereMorphedTo('attachable', $owner)
            ->where('collection', $collection->value)
            ->get();

        $this->ensureFreshUploads(array_diff($paths, $existing->pluck('path')->all()), $collection, $field);

        foreach ($existing as $attachment) {
            if (! in_array($attachment->path, $paths, true)) {
                $this->remove($attachment);
            }
        }

        $known = $existing->pluck('path')->all();

        foreach (array_diff($paths, $known) as $path) {
            $this->record($owner, $collection, $path, basename($path));
        }
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function ensureFreshUploads(array $paths, AttachmentCollection $collection, string $field): void
    {
        $directory = $this->storage->path($collection->directory()).'/';

        foreach ($paths as $path) {
            $valid = $this->storage->owns($path)
                && str_starts_with($path, $directory)
                && Storage::disk()->exists($path)
                && ! Attachment::query()->where('path', $path)->exists();

            if (! $valid) {
                throw ValidationException::withMessages([$field => 'Berkas tidak valid. Unggah ulang berkasnya.']);
            }
        }
    }

    public function store(Model $owner, AttachmentCollection $collection, UploadedFile $file): Attachment
    {
        $path = $this->storage->putFile($collection->directory(), $file);

        return $this->record($owner, $collection, $path, $file->getClientOriginalName());
    }

    /**
     * Raw contents of the file, decrypted when the collection is encrypted.
     */
    public function contents(Attachment $attachment): string
    {
        $contents = (string) Storage::disk($attachment->disk)->get($attachment->path);

        return $attachment->is_encrypted ? Crypt::decryptString($contents) : $contents;
    }

    private function record(Model $owner, AttachmentCollection $collection, string $path, string $originalName): Attachment
    {
        $disk = Storage::disk();
        $actor = $this->actors->current();

        $attachment = Attachment::create([
            'attachable_type' => $owner->getMorphClass(),
            'attachable_id' => $owner->getKey(),
            'collection' => $collection,
            'disk' => config('filesystems.default'),
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'size_bytes' => $disk->size($path),
            'is_encrypted' => $collection->isEncrypted(),
            'uploaded_by_type' => $actor->type,
            'uploaded_by_id' => $actor->id,
        ]);

        if ($collection->isEncrypted()) {
            $disk->put($path, Crypt::encryptString((string) $disk->get($path)));
        }

        return $attachment;
    }

    private function remove(Attachment $attachment): void
    {
        $attachment->delete();

        DB::afterCommit(fn () => Storage::disk($attachment->disk)->delete($attachment->path));
    }
}
