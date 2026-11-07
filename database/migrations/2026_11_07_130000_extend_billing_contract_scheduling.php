<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_contracts', function (Blueprint $table): void {
            $table->foreignId('compute_instance_id')->nullable()->after('party_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->after('compute_instance_id')->constrained()->restrictOnDelete();
            $table->string('name')->nullable()->after('number');
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->date('first_billing_date')->nullable();
            $table->unsignedInteger('billing_sequence')->default(0);
            $table->unsignedSmallInteger('generate_days_before')->default(14);
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->boolean('auto_generate')->default(true);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('currency', 3)->default('IDR');
            $table->unique(['billing_contract_id', 'period_start'], 'billing_contract_period_unique');
        });

        foreach (DB::table('billing_contracts')->orderBy('id')->get() as $contract) {
            $items = DB::table('contract_items')->where('billing_contract_id', $contract->id)->get();
            $item = $items->count() === 1 ? $items->first() : null;
            $target = $item?->target_type;
            DB::table('billing_contracts')->where('id', $contract->id)->update([
                'name' => $contract->description ?: $contract->number,
                'amount' => $item?->unit_price,
                'starts_at' => $contract->start_date,
                'ends_at' => $contract->end_date,
                'first_billing_date' => $contract->next_billing_date ?: $contract->start_date,
                'compute_instance_id' => $target === 'App\\Models\\ComputeInstance' ? $item->target_id : null,
                'service_id' => $target === 'App\\Models\\Service' ? $item->target_id : null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('billing_contract_period_unique');
            $table->dropColumn('currency');
        });
        Schema::table('billing_contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_id');
            $table->dropConstrainedForeignId('compute_instance_id');
            $table->dropColumn(['name', 'amount', 'currency', 'starts_at', 'ends_at', 'first_billing_date', 'billing_sequence', 'generate_days_before', 'payment_terms_days', 'auto_generate']);
        });
    }
};
