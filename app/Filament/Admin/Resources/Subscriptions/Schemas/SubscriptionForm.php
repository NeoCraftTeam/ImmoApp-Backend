<?php

namespace App\Filament\Admin\Resources\Subscriptions\Schemas;

use App\Enums\SubscriptionStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('agency_id')
                    ->relationship('agency', 'name')
                    ->required(),
                TextInput::make('subscription_plan_id')
                    ->required(),
                Select::make('status')
                    ->options(SubscriptionStatus::class)
                    ->default('pending')
                    ->required(),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                DateTimePicker::make('cancelled_at'),
                Select::make('payment_id')
                    ->relationship('payment', 'id'),
                TextInput::make('amount_paid')
                    ->numeric(),
                Toggle::make('auto_renew')
                    ->required(),
                Textarea::make('cancellation_reason')
                    ->columnSpanFull(),
                TextInput::make('billing_period')
                    ->required()
                    ->default('monthly'),
            ]);
    }
}
