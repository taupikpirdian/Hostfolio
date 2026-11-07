<?php

namespace App\Filament\Resources\BillingContracts\Pages;

use App\Filament\Resources\BillingContracts\BillingContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBillingContracts extends ManageRecords
{
    protected static string $resource = BillingContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
