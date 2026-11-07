<?php

namespace App\Filament\Pages;

class Renewals extends Reports
{
    protected static ?string $title = 'Renewals';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public string $report = 'renewals';

    public static function canAccess(): bool
    {
        return auth()->user()?->canView('billing') ?? false;
    }
}
