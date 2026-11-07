<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('type')->default('external');
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
        Schema::create('physical_servers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('provider_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('cpu_model')->nullable();
            $table->unsignedInteger('cpu_cores')->nullable();
            $table->unsignedBigInteger('ram_mb')->nullable();
            $table->decimal('storage_gb', 14, 2)->nullable();
            $table->string('operating_system')->nullable();
            $table->string('management_ip')->nullable();
            $table->string('status')->default('planned')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('compute_instances', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->index();
            $table->foreignId('provider_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('physical_server_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('compute_instances')->restrictOnDelete();
            $table->string('hostname')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('operating_system')->nullable();
            $table->string('external_reference')->nullable();
            $table->string('status')->default('planned')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('resource_allocation_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('compute_instance_id')->constrained()->restrictOnDelete();
            $table->decimal('vcpu', 10, 2)->nullable();
            $table->unsignedBigInteger('ram_mb')->nullable();
            $table->decimal('storage_gb', 14, 2)->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable()->index();
            $table->unsignedBigInteger('active_instance_id')->nullable()->storedAs('CASE WHEN effective_to IS NULL THEN compute_instance_id ELSE NULL END')->unique();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->index();
            $table->text('description')->nullable();
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status')->default('draft')->index();
            $table->date('started_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('legacy_data')->nullable();
            $table->timestamps();
        });
        Schema::create('service_deployments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('compute_instance_id')->constrained()->restrictOnDelete();
            $table->string('environment')->default('production');
            $table->string('role')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('deployed_at');
            $table->timestamp('removed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('active_marker')->nullable()->storedAs('CASE WHEN removed_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['service_id', 'compute_instance_id', 'environment', 'active_marker'], 'unique_active_deployment');
            $table->timestamps();
        });
        Schema::create('domains', function (Blueprint $table): void {
            $table->id();
            $table->string('fqdn')->unique();
            $table->string('registrar')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->boolean('auto_renew')->default(false);
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('service_domain', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('domain_id')->constrained()->restrictOnDelete();
            $table->unique(['service_id', 'domain_id']);
        });
    }

    public function down(): void
    {
        foreach (['service_domain', 'domains', 'service_deployments', 'services', 'resource_allocation_history', 'compute_instances', 'physical_servers', 'providers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
