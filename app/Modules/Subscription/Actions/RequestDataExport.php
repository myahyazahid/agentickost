<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Access\Audit\AuditLogger;
use App\Modules\Subscription\Jobs\ExportTenantData;
use App\Modules\Subscription\Support\CurrentSubscription;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use Illuminate\Validation\ValidationException;

/**
 * Starts an export of all the tenant's data (FR-SUB-06). Allowed in
 * read-only mode, since that is when an owner leaving needs it most. The
 * export itself runs on the queue; the link arrives as a notification.
 */
final class RequestDataExport extends Action implements AllowedWhenReadOnly
{
    public function __construct(
        private readonly ActorContext $actors,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(): void
    {
        $subscription = CurrentSubscription::get();
        $this->authorize('exportData', $subscription);

        $actor = $this->actors->current();
        $userId = $actor->type === ActorType::User ? $actor->id : $actor->user?->getAuthIdentifier();

        if (! is_string($userId)) {
            throw ValidationException::withMessages(['export' => 'Ekspor data hanya bisa diminta oleh pengguna yang login.']);
        }

        $this->transaction(fn () => $this->audit->record('tenant.data_exported', $subscription));

        ExportTenantData::dispatch($userId);
    }
}
