<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('type')->default('client');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->text('tax_information')->nullable();
            $t->text('notes')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('billing_contracts', function (Blueprint $t): void {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('party_id')->constrained()->restrictOnDelete();
            $t->string('direction');
            $t->text('description')->nullable();
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('billing_cycle');
            $t->date('next_billing_date')->nullable()->index();
            $t->string('status')->default('draft')->index();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('contract_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('billing_contract_id')->constrained()->restrictOnDelete();
            $t->string('description');
            $t->decimal('quantity', 12, 4)->default(1);
            $t->decimal('unit_price', 18, 2);
            $t->nullableMorphs('target');
            $t->date('effective_from');
            $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t): void {
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('source_currency', 3)->default('IDR');
            $t->decimal('source_amount', 18, 2)->nullable();
            $t->decimal('exchange_rate', 18, 6)->nullable();
            $t->string('exchange_rate_source')->nullable();
            $t->id();
            $t->string('number')->unique();
            $t->string('type');
            $t->foreignId('party_id')->constrained()->restrictOnDelete();
            $t->foreignId('billing_contract_id')->nullable()->constrained()->restrictOnDelete();
            $t->date('issue_date');
            $t->date('due_date')->index();
            $t->date('period_start')->nullable();
            $t->date('period_end')->nullable();
            $t->string('status')->default('draft')->index();
            foreach (['subtotal', 'discount', 'tax', 'total'] as $field) {
                $t->decimal($field, 18, 2)->default(0);
            } $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('invoice_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $t->string('description');
            $t->decimal('quantity', 12, 4)->default(1);
            foreach (['unit_price', 'discount', 'tax', 'total'] as $field) {
                $t->decimal($field, 18, 2)->default(0);
            } $t->nullableMorphs('target');
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $t->date('paid_at')->index();
            $t->decimal('amount', 18, 2);
            $t->string('method');
            $t->string('reference')->nullable();
            $t->string('proof_path')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('reversed_at')->nullable();
            $t->text('reversal_reason')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'invoice_items', 'invoices', 'contract_items', 'billing_contracts', 'parties'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
