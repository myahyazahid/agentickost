<?php

namespace App\Modules\Subscription\Console;

use App\Modules\Subscription\Jobs\ExportTenantData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes data exports whose download link has expired, so tenant data
 * does not linger in storage (FR-SUB-06).
 */
final class PruneDataExports extends Command
{
    protected $signature = 'subscriptions:prune-exports';

    protected $description = 'Hapus arsip ekspor data yang tautannya sudah kedaluwarsa';

    public function handle(): int
    {
        $cutoff = now()->subDays(ExportTenantData::DOWNLOAD_DAYS)->getTimestamp();
        $deleted = 0;

        foreach (Storage::directories('tenants') as $tenantDirectory) {
            foreach (Storage::files($tenantDirectory.'/exports') as $file) {
                if (Storage::lastModified($file) < $cutoff) {
                    Storage::delete($file);
                    $deleted++;
                }
            }
        }

        $this->components->info("{$deleted} arsip ekspor dihapus.");

        return self::SUCCESS;
    }
}
