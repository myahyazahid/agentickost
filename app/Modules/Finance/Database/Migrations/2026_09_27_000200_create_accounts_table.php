<?php

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\AccountType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('type', 16);
            $table->string('subtype', 32)->nullable();
            $table->foreignUlid('parent_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'subtype']);
        });

        Check::enum('accounts', 'type', AccountType::class);
        Check::enum('accounts', 'subtype', AccountSubtype::class, nullable: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
