<?php

namespace App\Filament\Admin\Resources\AdTypes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AdTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nom')
                    ->required(),
                Textarea::make('desc')->label('Description')
                    ->columnSpanFull(),
            ]);
    }


}
