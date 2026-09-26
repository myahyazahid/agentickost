<?php

use App\Support\Actors\ActorType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('actor_type', 32);
            $table->ulid('actor_id')->nullable();
            $table->string('event', 80);
            $table->string('subject_type', 80);
            $table->ulid('subject_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->foreignUlid('impersonation_log_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'subject_type', 'subject_id']);
            $table->index(['tenant_id', 'created_at']);
        });

        $actorTypes = implode(', ', array_map(fn (ActorType $type): string => "'{$type->value}'", ActorType::cases()));

        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_actor_type_check CHECK (actor_type IN ({$actorTypes}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
