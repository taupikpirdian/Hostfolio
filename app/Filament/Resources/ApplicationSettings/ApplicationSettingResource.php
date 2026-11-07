<?php

namespace App\Filament\Resources\ApplicationSettings;

use App\Models\ApplicationSetting;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApplicationSettingResource extends Resource
{
    protected static ?string $model = ApplicationSetting::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('key')->options(['organization_name' => 'Organization name', 'contact_email' => 'Contact email', 'address' => 'Organization address', 'payment_information' => 'Informasi Pembayaran', 'invoice_footer' => 'Invoice footer'])->required()->unique(ignoreRecord: true), Textarea::make('value')->maxLength(5000)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('key')->searchable(), TextColumn::make('value')->limit(80)])->defaultSort('id', 'desc')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageApplicationSettings::route('/')];
    }
}
