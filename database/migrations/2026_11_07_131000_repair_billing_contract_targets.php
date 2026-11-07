<?php

use App\Models\ComputeInstance;
use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('billing_contracts')->whereNull('compute_instance_id')->whereNull('service_id')->orderBy('id')->get() as $contract) {
            $items = DB::table('contract_items')->where('billing_contract_id', $contract->id)->get();
            if ($items->count() !== 1) {
                continue;
            }
            $item = $items->first();
            $target = [];
            if ($item->target_type === ComputeInstance::class) {
                $target['compute_instance_id'] = $item->target_id;
            } elseif ($item->target_type === Service::class) {
                $target['service_id'] = $item->target_id;
            }
            if ($target) {
                DB::table('billing_contracts')->where('id', $contract->id)->update($target);
            }
        }
    }

    public function down(): void {}
};
