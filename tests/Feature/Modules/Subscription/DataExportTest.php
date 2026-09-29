<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Lease\Models\Resident;
use App\Modules\Property\Models\Room;
use App\Modules\Subscription\Actions\AdvanceSubscription;
use App\Modules\Subscription\Actions\RequestDataExport;
use App\Modules\Subscription\Jobs\ExportTenantData;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Actors\Actor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    $this->travelTo('2026-10-05 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->tenant = tenancy()->tenant();
});

/**
 * @return array<string, string> file name => contents
 */
function exportedFiles(): array
{
    $paths = Storage::files('tenants/'.tenancy()->id().'/exports');
    expect($paths)->toHaveCount(1);

    $local = tempnam(sys_get_temp_dir(), 'export');
    file_put_contents($local, Storage::get($paths[0]));

    $zip = new ZipArchive;
    $zip->open($local);
    $files = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $files[(string) $zip->getNameIndex($i)] = (string) $zip->getFromIndex($i);
    }

    $zip->close();
    unlink($local);

    return $files;
}

function downloadLink(): string
{
    $notification = DatabaseNotification::query()->where('data->title', 'Ekspor data siap diunduh')->sole();

    return $notification->data['actions'][0]['url'];
}

it('exports only the tenant own data, without hidden columns', function () {
    Room::factory()->count(2)->create();
    Resident::factory()->create(['full_name' => '=HYPERLINK("x")', 'identity_number' => '3201010101010001']);
    $other = Tenant::factory()->create();
    $otherRoom = tenancy()->run($other, fn () => Room::factory()->create(['number' => 'LAIN-99']));

    app(RequestDataExport::class)->handle();

    $files = exportedFiles();

    expect($files)->toHaveKeys(['usaha.csv', 'room.csv', 'resident.csv', 'user.csv'])
        ->and(substr_count(trim($files['room.csv']), "\n"))->toBe(2)
        ->and($files['room.csv'])->not->toContain($otherRoom->id)
        ->and($files['usaha.csv'])->toContain($this->tenant->name)
        ->and($files['user.csv'])->toContain($this->owner->email)->not->toContain('password')
        ->and($files['resident.csv'])->toContain("'=HYPERLINK")->not->toContain('3201010101010001')
        ->and(AuditLog::query()->where('event', 'tenant.data_exported')->exists())->toBeTrue();
});

it('sends a download link only the owner can open', function () {
    app(RequestDataExport::class)->handle();
    $link = downloadLink();

    $this->get($link)->assertOk()->assertHeader('Content-Type', 'application/zip');

    loginAs(staff(Role::Manager, $this->tenant));
    $this->get($link)->assertForbidden();
});

it('keeps only the latest export and removes expired ones', function () {
    app(RequestDataExport::class)->handle();
    app(RequestDataExport::class)->handle();
    $files = Storage::files('tenants/'.$this->tenant->id.'/exports');

    expect($files)->toHaveCount(1);

    $this->artisan('subscriptions:prune-exports')->assertSuccessful()->expectsOutputToContain('0 arsip ekspor dihapus');

    touch(Storage::path($files[0]), now()->subDays(ExportTenantData::DOWNLOAD_DAYS + 1)->getTimestamp());
    $this->artisan('subscriptions:prune-exports')->assertSuccessful()->expectsOutputToContain('1 arsip ekspor dihapus');

    expect(Storage::files('tenants/'.$this->tenant->id.'/exports'))->toBe([]);
});

it('still exports when the tenant is read-only', function () {
    Queue::fake();
    $this->travelTo('2026-10-28 03:00:00');
    $this->tenant->update(['trial_ends_at' => '2026-10-19 16:59:59']);
    actors()->actingAs(Actor::system(), fn () => app(AdvanceSubscription::class)->handle());

    app(RequestDataExport::class)->handle();

    Queue::assertPushed(ExportTenantData::class, fn (ExportTenantData $job): bool => $job->userId === $this->owner->id);
});

it('only lets the owner export', function () {
    loginAs(staff(Role::Accountant, $this->tenant));

    app(RequestDataExport::class)->handle();
})->throws(AuthorizationException::class);
