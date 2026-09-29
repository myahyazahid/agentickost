<?php

use App\Support\Actors\ActorType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the actor constraint from ActorType, which gained payer for
 * payers who log in to the resident portal (FR-PRT-07).
 */
return new class extends Migration
{
    public function up(): void
    {
        $actorTypes = implode(', ', array_map(fn (ActorType $type): string => "'{$type->value}'", ActorType::cases()));

        DB::statement('ALTER TABLE audit_logs DROP CHECK audit_logs_actor_type_check');
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_actor_type_check CHECK (actor_type IN ({$actorTypes}))");
    }

    public function down(): void
    {
        // The wider constraint stays; older values are a subset of it.
    }
};
