<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('invoice.number'),
            TextEntry::make('paid_at')->date(),
            TextEntry::make('amount')->money('IDR'),
            TextEntry::make('method'),
            TextEntry::make('notes'),
            ViewEntry::make('proof_path')
                ->view('filament.resources.payments.proof-preview')
                ->viewData(fn (Payment $record): array => [
                    'payment' => $record,
                    'previewUrl' => URL::temporarySignedRoute('payments.proof', now()->addMinutes(5), ['payment' => $record->id, 'preview' => 1]),
                ])
                ->columnSpanFull()
                ->visible(fn (Payment $record): bool => filled($record->proof_path)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('invoice'))
            ->columns([
                TextColumn::make('invoice.number')->searchable(),
                TextColumn::make('paid_at')->date()->sortable(),
                TextColumn::make('amount')->money('IDR')->sortable(),
                TextColumn::make('method'),
            ])
            ->filters([
                Filter::make('valid')->query(fn (Builder $query) => $query->whereNull('reversed_at')),
                Filter::make('reversed')->query(fn (Builder $query) => $query->whereNotNull('reversed_at')),
            ])
            ->recordActions([ViewAction::make()->modalWidth('5xl')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePayments::route('/')];
    }
}
