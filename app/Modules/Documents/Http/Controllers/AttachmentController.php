<?php

namespace App\Modules\Documents\Http\Controllers;

use App\Modules\Access\Audit\AuditLogger;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Documents\Support\AttachmentSync;
use finfo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Streams an encrypted attachment (an identity document) behind a
 * short-lived signed URL, decrypted here and never exposed on the disk URL.
 *
 * The link alone is not enough: the viewer must still be allowed to see the
 * resident's identity, and each opening is written to the audit log
 * (NFR-PDP-04). The file type is read from the decrypted bytes, and only
 * images and PDFs open in the browser; anything else downloads, so an
 * uploaded page can never run as the app.
 */
final class AttachmentController
{
    /**
     * @var list<string>
     */
    private const INLINE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function __invoke(Request $request, AttachmentSync $attachments, AuditLogger $audit, string $attachment): Response
    {
        $file = Attachment::query()->whereKey($attachment)->where('is_encrypted', true)->firstOrFail();
        $owner = $file->attachable()->firstOrFail();

        Gate::forUser($request->user())->authorize('viewIdentity', $owner);

        $contents = $attachments->contents($file);
        $type = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';
        $inline = in_array($type, self::INLINE_TYPES, true);

        $audit->record('attachment.opened', $file, null, ['collection' => $file->collection->value, 'attachable_id' => $file->attachable_id]);

        $name = str_replace(['/', '\\'], '-', $file->original_name);

        return response($contents, 200, [
            'Content-Type' => $inline ? $type : 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $name,
                (string) preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($name)) ?: 'berkas',
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
