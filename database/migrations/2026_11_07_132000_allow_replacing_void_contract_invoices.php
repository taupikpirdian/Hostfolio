<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->date('active_period_start')->nullable()->virtualAs("CASE WHEN status = 'void' THEN NULL ELSE period_start END");
            $table->unique(['billing_contract_id', 'active_period_start'], 'billing_contract_active_period_unique');
            $table->dropUnique('billing_contract_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unique(['billing_contract_id', 'period_start'], 'billing_contract_period_unique');
            $table->dropUnique('billing_contract_active_period_unique');
            $table->dropColumn('active_period_start');
        });
    }
};
