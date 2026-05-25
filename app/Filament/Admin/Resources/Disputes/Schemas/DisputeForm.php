<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Disputes\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DisputeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Litige')
                ->icon(Heroicon::OutlinedScale)
                ->columns(2)
                ->schema([
                    Placeholder::make('reference')
                        ->label('Référence')
                        ->content(fn ($record) => $record?->reference ?: '—'),
                    Placeholder::make('status_label')
                        ->label('Statut actuel')
                        ->content(fn ($record) => $record?->status?->getLabel() ?: '—'),
                    Placeholder::make('type_label')
                        ->label('Type')
                        ->content(fn ($record) => $record?->type?->getLabel() ?: '—'),
                    Placeholder::make('amount_claimed')
                        ->label('Montant réclamé')
                        ->content(fn ($record) => $record?->amount_claimed
                            ? number_format((int) $record->amount_claimed, 0, ',', ' ').' FCFA'
                            : '—'),
                    Placeholder::make('title')
                        ->label('Titre')
                        ->content(fn ($record) => $record?->title ?: '—')
                        ->columnSpanFull(),
                    Placeholder::make('description')
                        ->label('Description')
                        ->content(fn ($record) => $record?->description ?: '—')
                        ->columnSpanFull(),
                ]),

            Section::make('Parties')
                ->icon(Heroicon::OutlinedUsers)
                ->columns(2)
                ->schema([
                    Placeholder::make('initiator')
                        ->label('Initiateur')
                        ->content(fn ($record) => $record?->initiator?->fullname ?: '—'),
                    Placeholder::make('respondent')
                        ->label('Défendeur')
                        ->content(fn ($record) => $record?->respondent?->fullname ?: '—'),
                    Placeholder::make('admin')
                        ->label('Admin en charge')
                        ->content(fn ($record) => $record?->admin?->fullname ?: 'Aucun assigné'),
                    Placeholder::make('sla_deadline')
                        ->label('Échéance SLA')
                        ->content(fn ($record) => $record?->sla_deadline?->format('d/m/Y H:i') ?: '—'),
                ]),

            Section::make('Résolution')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->visible(fn ($record) => $record?->resolved_at !== null)
                ->columns(2)
                ->schema([
                    Placeholder::make('resolved_at')
                        ->label('Résolu le')
                        ->content(fn ($record) => $record?->resolved_at?->format('d/m/Y H:i') ?: '—'),
                    Placeholder::make('resolution_note')
                        ->label('Note de résolution')
                        ->content(fn ($record) => $record?->resolution_note ?: '—')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
