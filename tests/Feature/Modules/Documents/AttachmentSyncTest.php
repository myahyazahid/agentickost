<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Property\Actions\CreateProperty;
use App\Modules\Property\Actions\UpdateProperty;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Support\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Put a file where Filament's upload field would, under the tenant directory.
 */
function uploadedPhoto(string $name): string
{
    $path = app(TenantStorage::class)->path(AttachmentCollection::Photo->directory().'/'.$name);
    Storage::put($path, 'jpeg-bytes');

    return $path;
}

/**
 * @return array<string, mixed>
 */
function propertyWithPhotos(array $photos): array
{
    return [
        'name' => 'Kost Melati', 'code' => 'MLT', 'address' => 'Jl. Melati 5', 'city' => 'Bandung',
        'province' => 'Jawa Barat', 'timezone' => 'Asia/Jakarta', 'gender_policy' => 'female',
        'photos' => $photos,
    ];
}

it('records uploaded photos and removes dropped ones with their files', function () {
    Storage::fake();
    loginAs(staff(Role::Owner));
    [$front, $room] = [uploadedPhoto('depan.jpg'), uploadedPhoto('kamar.jpg')];
    $property = app(CreateProperty::class)->handle(propertyWithPhotos([$front, $room]));

    app(UpdateProperty::class)->handle($property, propertyWithPhotos([$room]));

    expect($property->attachmentPaths(AttachmentCollection::Photo))->toBe([$room]);
    Storage::assertMissing($front);
    Storage::assertExists($room);
});

it('refuses a path outside the tenant directory', function () {
    Storage::fake();
    loginAs(staff(Role::Owner));

    app(CreateProperty::class)->handle(propertyWithPhotos(['tenants/01jaaaaaaaaaaaaaaaaaaaaaaa/uploads/photo/x.jpg']));
})->throws(ValidationException::class, 'Berkas tidak valid.');

it('encrypts identity documents at rest', function () {
    Storage::fake();
    loginAs(staff(Role::Owner));
    $property = Property::factory()->create();

    $attachment = app(AttachmentSync::class)->store(
        $property,
        AttachmentCollection::Identity,
        UploadedFile::fake()->createWithContent('ktp.jpg', 'isi-ktp-asli'),
    );

    expect($attachment->is_encrypted)->toBeTrue()
        ->and(Storage::get($attachment->path))->not->toContain('isi-ktp-asli')
        ->and(app(AttachmentSync::class)->contents($attachment))->toBe('isi-ktp-asli')
        ->and(Attachment::query()->count())->toBe(1);
});
