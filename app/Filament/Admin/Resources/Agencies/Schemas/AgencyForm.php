<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Agencies\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AgencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('logo'),
                Select::make('owner_id')
                    ->relationship('owner', 'id'),
            ]);
    }
}
