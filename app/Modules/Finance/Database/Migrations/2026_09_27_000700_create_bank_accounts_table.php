<?php

use App\Modules\Finance\Enums\BankAccountKind;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->string('provider_name', 80);
            $table->string('account_number', 40);
            $table->string('account_holder', 100);
            $table->foreignUlid('ledger_account_id')->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'is_active']);
        });

        Check::enum('bank_accounts', 'kind', BankAccountKind::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
