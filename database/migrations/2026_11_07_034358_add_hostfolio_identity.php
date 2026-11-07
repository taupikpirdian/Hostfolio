<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('viewer')->index();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->json('permissions');
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
        });
        foreach (['inventory.view', 'inventory.manage', 'billing.view', 'billing.manage', 'settings.view', 'settings.manage'] as $p) {
            DB::table('permissions')->insert(['name' => $p]);
        }
        foreach (['admin' => ['*'], 'operator' => ['inventory.view', 'inventory.manage'], 'finance' => ['billing.view', 'billing.manage'], 'viewer' => ['inventory.view', 'billing.view']] as $name => $permissions) {
            DB::table('roles')->insert(['name' => $name, 'permissions' => json_encode($permissions), 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('changes')->nullable();
            $t->string('ip_address')->nullable();
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
        });
        Schema::create('application_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('type')->default('expense');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['categories', 'application_settings', 'audit_logs', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'is_active']));
    }
};
