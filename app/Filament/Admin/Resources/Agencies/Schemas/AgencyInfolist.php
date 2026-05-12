<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Agencies\Schemas;

use App\Models\Agency;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AgencyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                // ── Identité ──────────────────────────────────────────────────
                Section::make('Identité de l\'agence')
                    ->icon(Heroicon::BuildingOffice2)
                    ->iconColor('primary')
                    ->description('Profil public et informations de l\'agence')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('logo')
                            ->label('')
                            ->circular()
                            ->disk(config('filesystems.app_media_disk', 'public'))
                            ->defaultImageUrl(fn (Agency $record): string => 'https://ui-avatars.com/api/?name='.urlencode($record->name)
                                .'&background=F6475F&color=fff&bold=true'
                            )
                            ->size(80)
                            ->columnSpanFull(),
                        TextEntry::make('name')
                            ->label('Nom de l\'agence')
                            ->weight('bold')
                            ->size('lg')
                            ->icon(Heroicon::BuildingOffice2)
                            ->iconColor('primary')
                            ->columnSpanFull(),
                        TextEntry::make('slug')
                            ->label('Slug URL')
                            ->icon(Heroicon::Link)
                            ->iconColor('gray')
                            ->copyable()
                            ->copyMessage('Slug copié !')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('owner.fullname')
                            ->label('Propriétaire')
                            ->icon(Heroicon::UserCircle)
                            ->iconColor('success')
                            ->placeholder('Aucun propriétaire'),
                    ]),

                // ── Statistiques ─────────────────────────────────────────────
                Section::make('Statistiques')
                    ->icon(Heroicon::ChartBar)
                    ->iconColor('info')
                    ->description('Activité et abonnement de l\'agence')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('ads_count')
                            ->label('Annonces publiées')
                            ->getStateUsing(fn (Agency $record): int => $record->users()->withCount('ads')->get()->sum('ads_count'))
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::Megaphone),
                        TextEntry::make('members_count')
                            ->label('Membres')
                            ->getStateUsing(fn (Agency $record): int => $record->users()->count())
                            ->badge()
                            ->color('primary')
                            ->icon(Heroicon::Users),
                        TextEntry::make('subscription_status')
                            ->label('Abonnement actif')
                            ->getStateUsing(function (Agency $record): string {
                                $sub = $record->getCurrentSubscription();

                                return (string) data_get($sub, 'plan.name', 'Aucun');
                            })
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Aucun' ? 'gray' : 'success'),
                    ]),

                // ── Métadonnées ────────────────────────────────────────────────
                Section::make('Métadonnées')
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id')
                            ->label('Identifiant')
                            ->copyable()
                            ->copyMessage('ID copié !')
                            ->badge()
                            ->color('gray')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label('Créée le')
                            ->icon(Heroicon::CalendarDays)
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Modifiée le')
                            ->icon(Heroicon::PencilSquare)
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label('Supprimée le')
                            ->icon(Heroicon::Trash)
                            ->iconColor('danger')
                            ->dateTime('d/m/Y à H:i')
                            ->visible(fn (Agency $record): bool => $record->trashed()),
                    ]),
            ]);
    }
}
