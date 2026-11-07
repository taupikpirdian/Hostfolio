<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('staged');
            $t->json('mapping');
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('import_batch_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('row_number');
            $t->json('raw');
            $t->json('normalized');
            $t->json('errors');
            $t->unsignedBigInteger('service_id')->nullable();
            $t->timestamps();
            $t->unique(['import_batch_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
