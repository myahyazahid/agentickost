<?php

use App\Modules\Lease\Enums\Gender;
use App\Modules\Lease\Enums\IdentityType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('full_name', 150);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->string('gender', 8)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('identity_type', 16)->nullable();
            $table->text('identity_number')->nullable();
            $table->char('identity_number_hash', 64)->nullable();
            $table->string('institution', 150)->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 40)->nullable();
            $table->string('vehicle_plate', 20)->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'identity_number_hash']);
        });

        Check::enum('residents', 'gender', Gender::class, nullable: true);
        Check::enum('residents', 'identity_type', IdentityType::class, nullable: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
