<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Agencies\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AgencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations de l\'agence')
                    ->icon(Heroicon::BuildingOffice2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->helperText('Nom officiel de l\'agence'),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->helperText('Identifiant URL unique'),
                        FileUpload::make('logo')
                            ->label('Logo')
                            ->disk('public')
                            ->directory('agency-logos')
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->helperText('Logo de l\'agence (format carré recommandé)'),
                        Select::make('owner_id')
                            ->label('Propriétaire')
                            ->relationship(
                                'owner',
                                'firstname',
                                modifyQueryUsing: fn ($query) => $query->with('agency'),
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->fullname)
                            ->searchable()
                            ->preload()
                            ->helperText('L\'utilisateur qui gère cette agence'),
                    ]),
            ]);
    }
}
