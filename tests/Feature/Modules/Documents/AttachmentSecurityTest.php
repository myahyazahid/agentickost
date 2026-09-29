<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Filament\AttachmentUpload;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Lease\Actions\CreateResident;
use App\Modules\Lease\Models\Resident;
use App\Modules\Property\Actions\CreateProperty;
use App\Modules\Property\Actions\UpdateProperty;
use App\Modules\Tenancy\Support\TenantStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
 * Uploaded file paths come from form state the browser controls (NFR-SEC-04).
 * A path must be a fresh upload of the right kind, and identity documents
 * open only for people allowed to see them.
 */
beforeEach(function () {
    Storage::fake();
    $this->owner = loginAs(staff(Role::Owner));
});

function freshUpload(AttachmentCollection $collection, string $name, string $contents = 'jpeg-bytes'): string
{
    $path = app(TenantStorage::class)->path($collection->directory().'/'.$name);
    Storage::put($path, $contents);

    return $path;
}

/**
 * @param  list<string>  $photos
 * @return array<string, mixed>
 */
function attachmentTestProperty(string $code, array $photos): array
{
    return [
        'name' => "Kost {$code}", 'code' => $code, 'address' => 'Jl. Melati 5', 'city' => 'Bandung',
        'province' => 'Jawa Barat', 'timezone' => 'Asia/Jakarta', 'gender_policy' => 'mixed',
        'photos' => $photos,
    ];
}

it('refuses to take over a file that already belongs to another record', function () {
    $photo = freshUpload(AttachmentCollection::Photo, 'depan.jpg');
    app(CreateProperty::class)->handle(attachmentTestProperty('MLT', [$photo]));

    expect(fn () => app(CreateProperty::class)->handle(attachmentTestProperty('ANG', [$photo])))
        ->toThrow(ValidationException::class, 'Berkas tidak valid');

    Storage::assertExists($photo);
});

it('refuses a path from another kind of upload, or one that is not on disk', function () {
    $proof = freshUpload(AttachmentCollection::PaymentProof, 'bukti.jpg');

    expect(fn () => app(CreateProperty::class)->handle(attachmentTestProperty('MLT', [$proof])))->toThrow(ValidationException::class)
        ->and(fn () => app(CreateProperty::class)->handle(attachmentTestProperty('ANG', [app(TenantStorage::class)->path('uploads/photo/tidak-ada.jpg')])))->toThrow(ValidationException::class);
});

it('keeps a record\'s own files when it is saved again', function () {
    $photo = freshUpload(AttachmentCollection::Photo, 'depan.jpg');
    $property = app(CreateProperty::class)->handle(attachmentTestProperty('MLT', [$photo, $photo]));

    app(UpdateProperty::class)->handle($property, attachmentTestProperty('MLT', [$photo]));

    expect($property->attachmentPaths(AttachmentCollection::Photo))->toBe([$photo]);
});

it('only lets upload fields reuse files of the record, or of records the user may view', function () {
    $photo = freshUpload(AttachmentCollection::Photo, 'depan.jpg');
    $property = app(CreateProperty::class)->handle(attachmentTestProperty('MLT', [$photo]));
    $other = app(CreateProperty::class)->handle(attachmentTestProperty('ANG', []));

    expect(AttachmentUpload::mayReuse($photo, AttachmentCollection::Photo, $property))->toBeTrue()
        ->and(AttachmentUpload::mayReuse($photo, AttachmentCollection::Photo, $other))->toBeFalse()
        ->and(AttachmentUpload::mayReuse($photo, AttachmentCollection::Identity, null))->toBeFalse()
        ->and(AttachmentUpload::mayReuse(app(TenantStorage::class)->path('uploads/photo/lain.jpg'), AttachmentCollection::Photo, null))->toBeFalse();
});

it('accepts no SVG or HTML in any upload field', function () {
    foreach (AttachmentCollection::cases() as $collection) {
        expect($collection->acceptedMimeTypes())->not->toContain('image/svg+xml')->not->toContain('text/html');
    }
});

function residentWithIdentityFile(string $contents): Resident
{
    return app(CreateResident::class)->handle([
        'full_name' => 'Rina Wulandari',
        'phone' => '0812 3456 7890',
        'identity_type' => 'ktp',
        'identity_number' => '3174012345670001',
        'identity_documents' => [freshUpload(AttachmentCollection::Identity, 'ktp.jpg', $contents)],
    ]);
}

it('opens an identity document for the owner, audits it, and marks it as not sniffable', function () {
    $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHR8eHRocHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==');
    $attachment = residentWithIdentityFile($jpeg)->attachments()->sole();

    $this->get($attachment->temporaryUrl())
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect(AuditLog::query()->where('event', 'attachment.opened')->where('subject_id', $attachment->id)->exists())->toBeTrue();
});

it('downloads, never renders, an identity file that is not an image or PDF', function () {
    $attachment = residentWithIdentityFile('<html><script>alert(1)</script></html>')->attachments()->sole();

    $response = $this->get($attachment->temporaryUrl())->assertOk()->assertHeader('Content-Type', 'application/octet-stream');

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('refuses an identity document to staff who may not see identities, even with the link', function () {
    $attachment = residentWithIdentityFile('jpeg-bytes')->attachments()->sole();
    $url = $attachment->temporaryUrl();

    loginAs(staff(Role::Caretaker, $this->owner->tenant()->firstOrFail()));

    $this->get($url)->assertForbidden();
    expect(Attachment::query()->count())->toBe(1);
});
