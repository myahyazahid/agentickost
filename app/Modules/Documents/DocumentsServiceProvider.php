<?php

namespace App\Modules\Documents;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Documents\Enums\DocumentPermission;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Documents\Models\DocumentSequence;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class DocumentsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(DocumentPermission::class),
        );
    }

    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            'attachment' => Attachment::class,
            'document_sequence' => DocumentSequence::class,
        ]);
    }
}
