<?php

namespace App\Filament\Resources\AuditLogs;

use App\Models\AuditLog;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('event')->disabled()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('created_at')->dateTime()->sortable(), TextColumn::make('user_id')->label('User ID'), TextColumn::make('event')->searchable(), TextColumn::make('subject_type')->searchable(), TextColumn::make('subject_id'), TextColumn::make('changes')->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : $state)->limit(100)->tooltip(fn ($record) => json_encode($record->changes))])->defaultSort('id', 'desc')->recordActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAuditLogs::route('/')];
    }
}
