<?php

use App\Modules\Lease\Enums\PayerRelation;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('resident_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->string('relation', 16);
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'phone']);
        });

        Check::enum('payers', 'relation', PayerRelation::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('payers');
    }
};
