<?php

namespace App\Actions\Billing;

use App\Models\BillingContract;
use App\Models\ComputeInstance;
use App\Models\Party;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateBillingContract
{
    public function execute(ComputeInstance $instance, array $data): BillingContract
    {
        Gate::authorize('view', $instance);
        Gate::authorize('create', BillingContract::class);
        $validated = Validator::make($data, [
            'number' => 'required|string|max:255|unique:billing_contracts,number',
            'name' => 'required|string|max:255',
            'direction' => ['required', Rule::in(['income', 'expense'])],
            'party_id' => 'required|integer|exists:parties,id',
            'amount' => 'required|numeric|gte:0|max:9999999999999999.99',
            'currency' => 'required|regex:/^[A-Z]{3}$/',
            'billing_cycle' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'one_time'])],
            'starts_at' => 'required|date', 'first_billing_date' => 'required|date|after_or_equal:starts_at',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'generate_days_before' => 'required|integer|min:0|max:365', 'payment_terms_days' => 'required|integer|min:0|max:3650',
            'auto_generate' => 'required|boolean', 'status' => ['required', Rule::in(['draft', 'active', 'paused', 'ended'])], 'notes' => 'nullable|string',
        ])->validate();
        $partyType = $validated['direction'] === 'income' ? 'client' : 'vendor';
        $party = Party::query()->findOrFail($validated['party_id']);
        if (! in_array($party->type, [$partyType, 'both'], true)) {
            throw ValidationException::withMessages(['party_id' => 'Jenis party tidak sesuai dengan arah kontrak.']);
        }
        if ($validated['auto_generate'] && $validated['status'] !== 'active') {
            throw ValidationException::withMessages(['auto_generate' => 'Auto generate hanya dapat diaktifkan pada kontrak aktif.']);
        }
        if ($validated['ends_at'] && $validated['first_billing_date'] > $validated['ends_at']) {
            throw ValidationException::withMessages(['first_billing_date' => 'Tanggal tagihan pertama melewati akhir kontrak.']);
        }

        return DB::transaction(function () use ($instance, $validated): BillingContract {
            return BillingContract::create([
                ...$validated,
                'party_id' => $validated['party_id'],
                'compute_instance_id' => $instance->id,
                'starts_at' => $validated['starts_at'],
                'start_date' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'end_date' => $validated['ends_at'],
                'first_billing_date' => $validated['first_billing_date'],
                'next_billing_date' => $validated['first_billing_date'],
                'billing_sequence' => 0,
                'description' => $validated['name'],
            ]);
        });
    }
}
