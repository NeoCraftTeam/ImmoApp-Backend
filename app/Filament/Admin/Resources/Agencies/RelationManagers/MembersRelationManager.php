<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Agencies\RelationManagers;

use App\Enums\UserRole;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Membres';

    protected static string|BackedEnum|null $icon = Heroicon::Users;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('firstname')
            ->columns([
                ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular()
                    ->size(40)
                    ->disk(config('filesystems.app_media_disk'))
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->firstname.' '.$record->lastname).'&background=F6475F&color=fff'),
                TextColumn::make('full_name')
                    ->label('Nom')
                    ->formatStateUsing(fn ($record) => $record->firstname.' '.$record->lastname)
                    ->searchable(['firstname', 'lastname']),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('role')
                    ->label('Rôle')
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(UserRole::class)
                    ->label('Rôle'),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
