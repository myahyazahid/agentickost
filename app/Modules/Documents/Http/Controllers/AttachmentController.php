<?php

namespace App\Modules\Documents\Http\Controllers;

use App\Modules\Documents\Models\Attachment;
use App\Modules\Documents\Support\AttachmentSync;
use Illuminate\Http\Response;

/**
 * Streams one attachment behind a short-lived signed URL. Encrypted files
 * (identity documents) are decrypted here, never exposed on the disk URL.
 *
 * The attachment is looked up after the tenant middleware ran, so it is
 * resolved inside the logged-in user's tenant only.
 */
final class AttachmentController
{
    public function __invoke(AttachmentSync $attachments, string $attachment): Response
    {
        $file = Attachment::query()->whereKey($attachment)->firstOrFail();

        return response($attachments->contents($file), 200, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($file->original_name).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
