<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Surveys;

use App\Filament\Admin\Resources\Surveys\Pages\ListSurveys;
use App\Filament\Admin\Resources\Surveys\Pages\ViewSurvey;
use App\Models\Survey;
use App\Models\SurveyResponse;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|\UnitEnum $navigationGroup = 'Utilisateurs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static ?string $navigationLabel = 'Sondages';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Sondage';

    protected static ?string $pluralModelLabel = 'Sondages';

    protected static ?int $navigationSort = 5;

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['questions', 'responses'])
            ->selectSub(
                SurveyResponse::query()
                    ->selectRaw('COUNT(DISTINCT user_id)')
                    ->whereColumn('survey_id', 'surveys.id'),
                'respondents_count'
            );
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations du sondage')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Titre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true)
                            ->helperText('Seuls les sondages actifs sont visibles par les clients'),
                        Toggle::make('is_public')
                            ->label('Visible publiquement')
                            ->default(false)
                            ->helperText('Si activé, ce sondage apparaît sur /surveys pour les répondants anonymes'),
                    ]),
            ]);
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('title')
                            ->label('Titre')
                            ->columnSpan(3)
                            ->size('lg')
                            ->weight('bold'),
                        TextEntry::make('created_at')
                            ->label('Créé le')
                            ->dateTime('d/m/Y')
                            ->columnSpan(1),
                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->placeholder('Aucune description'),
                        IconEntry::make('is_active')
                            ->label('Statut')
                            ->boolean()
                            ->trueIcon(Heroicon::CheckCircle)
                            ->falseIcon(Heroicon::XCircle)
                            ->trueColor('success')
                            ->falseColor('danger'),
                        IconEntry::make('is_public')
                            ->label('Visible publiquement')
                            ->boolean()
                            ->trueIcon(Heroicon::GlobeAlt)
                            ->falseIcon(Heroicon::LockClosed)
                            ->trueColor('success')
                            ->falseColor('gray'),
                        ViewEntry::make('share_link')
                            ->label('Lien de partage')
                            ->view('filament.surveys.share-link')
                            ->columnSpan(2),
                        TextEntry::make('questions_count')
                            ->label('Questions')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('respondents_count')
                            ->label('Répondants uniques')
                            ->badge()
                            ->color('success'),
                        TextEntry::make('anonymous_responses_count')
                            ->label('Répondants anonymes')
                            ->badge()
                            ->color('warning')
                            ->getStateUsing(fn (Survey $record): int => $record->anonymousResponses()->count()),
                    ]),

                Section::make('Réponses des clients')
                    ->icon(Heroicon::Users)
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Infolists\Components\ViewEntry::make('respondents_view')
                            ->label('')
                            ->columnSpanFull()
                            ->view('filament.surveys.respondents-infolist'),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Sondages')
            ->description('Gestion des sondages de satisfaction clients')
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->weight('bold')
                    ->wrap(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle),
                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean()
                    ->trueIcon(Heroicon::GlobeAlt)
                    ->falseIcon(Heroicon::LockClosed)
                    ->toggleable(),
                TextColumn::make('questions_count')
                    ->label('Questions')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('respondents_count')
                    ->label('Répondants')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\CreateAction::make(),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListSurveys::route('/'),
            'view' => ViewSurvey::route('/{record}'),
            'edit' => Pages\EditSurvey::route('/{record}/edit'),
            'create' => Pages\CreateSurvey::route('/create'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var class-string<Survey> $model */
        $model = static::getModel();
        $count = $model::active()->count();

        return $count > 0 ? (string) $count : null;
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Sondages actifs';
    }
}
