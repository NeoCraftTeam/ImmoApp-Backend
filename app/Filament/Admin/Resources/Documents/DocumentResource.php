<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Documents;

use App\Enums\AdminPermission;
use App\Filament\Admin\Resources\Documents\Pages\ManageDocuments;
use App\Models\Document;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static bool $isScopedToTenant = false;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::UsersView) ?? false;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::PaperClip;

    protected static string|null|\UnitEnum $navigationGroup = 'Membres';

    protected static ?int $navigationSort = 10;

    protected static ?string $label = 'Document (KYC)';

    protected static ?string $pluralLabel = 'Documents (KYC)';

    protected static ?string $navigationLabel = 'Documents KYC';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'ad'])
            ->latest();
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Document')
                    ->icon(Heroicon::PaperClip)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom')
                            ->icon(Heroicon::Document),
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge(),
                        TextEntry::make('user.name')
                            ->label('Propriétaire')
                            ->icon(Heroicon::User),
                        TextEntry::make('ad.title')
                            ->label('Annonce associée'),
                        TextEntry::make('mime_type')
                            ->label('MIME type'),
                        TextEntry::make('file_size')
                            ->label('Taille (octets)')
                            ->numeric(),
                        TextEntry::make('file_path')
                            ->label('Chemin fichier')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label('Uploadé le')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mime_type')
                    ->label('MIME'),
                TextColumn::make('file_size')
                    ->label('Taille')
                    ->numeric()
                    ->suffix(' o'),
                TextColumn::make('created_at')
                    ->label('Uploadé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'id_card' => "Pièce d'identité",
                        'passport' => 'Passeport',
                        'lease' => 'Bail',
                        'proof_of_address' => 'Justificatif de domicile',
                        'other' => 'Autre',
                    ])
                    ->label('Type'),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageDocuments::route('/'),
        ];
    }
}
