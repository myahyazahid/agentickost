<?php

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores uploads under the tenant directory', function () {
    Storage::fake();
    $tenant = Tenant::factory()->create();

    $path = tenancy()->run($tenant, fn () => app(TenantStorage::class)->putFile('payment-proofs', UploadedFile::fake()->image('bukti.jpg')));

    expect($path)->toStartWith("tenants/{$tenant->id}/payment-proofs/");
    Storage::assertExists($path);
});

it('issues a temporary url only for files of the current tenant', function () {
    Storage::fake();
    Storage::disk()->buildTemporaryUrlsUsing(fn (string $path) => "https://files.test/{$path}");
    $tenant = Tenant::factory()->create();

    $url = tenancy()->run($tenant, fn () => app(TenantStorage::class)->temporaryUrl("tenants/{$tenant->id}/photos/kamar.jpg"));

    expect($url)->toBe("https://files.test/tenants/{$tenant->id}/photos/kamar.jpg");
});

it('refuses a temporary url for another tenant\'s file', function (string $path) {
    Storage::fake();
    $tenant = Tenant::factory()->create();

    tenancy()->run($tenant, fn () => app(TenantStorage::class)->temporaryUrl(str_replace('{tenant}', $tenant->id, $path)));
})->with([
    'another tenant' => 'tenants/01jaaaaaaaaaaaaaaaaaaaaaaa/photos/kamar.jpg',
    'path traversal' => 'tenants/{tenant}/../01jaaaaaaaaaaaaaaaaaaaaaaa/photos/kamar.jpg',
])->throws(RuntimeException::class);
