<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Surveys;

use App\Enums\AdminAsyncExportType;
use App\Enums\AdminPermission;
use App\Filament\Admin\Resources\Surveys\Pages\ListSurveys;
use App\Filament\Admin\Resources\Surveys\Pages\ViewSurvey;
use App\Jobs\Admin\ProcessAdminAsyncExportJob;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|\UnitEnum $navigationGroup = 'Membres';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static ?string $navigationLabel = 'Sondages';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Sondage';

    protected static ?string $pluralModelLabel = 'Sondages';

    protected static ?int $navigationSort = 5;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::SurveysManage) ?? false;
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::SurveysManage) ?? false;
    }

    #[\Override]
    public static function canEdit($record): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::SurveysManage) ?? false;
    }

    #[\Override]
    public static function canDelete($record): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::SurveysManage) ?? false;
    }

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['questions', 'responses', 'anonymousResponses'])
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
                        ViewEntry::make('respondents_view')
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
                    ->state(fn (Survey $record): int => (int) ($record->respondents_count ?? 0) + (int) ($record->anonymous_responses_count ?? 0))
                    ->badge()
                    ->color('success'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Actif')
                    ->trueLabel('Actifs')
                    ->falseLabel('Inactifs')
                    ->native(false),
                TernaryFilter::make('is_public')
                    ->label('Visibilité')
                    ->trueLabel('Publics')
                    ->falseLabel('Privés')
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('questions')
                    ->label('Questions')
                    ->icon(Heroicon::QuestionMarkCircle)
                    ->color('gray')
                    ->url(fn (Survey $record): string => SurveyTemplateResource::getUrl('edit', ['record' => $record])),
                EditAction::make(),
                Action::make('exportResponses')
                    ->label('Export CSV')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('success')
                    ->tooltip('Générer un CSV de toutes les réponses (clients + anonymes) — notification quand prêt')
                    ->action(function (Survey $record): void {
                        /** @var User $user */
                        $user = auth()->user();
                        ProcessAdminAsyncExportJob::dispatch($user->id, AdminAsyncExportType::SurveyResponsesCsv, $record->id);
                        Notification::make()
                            ->title('Export en file d\'attente')
                            ->body('Le CSV des réponses sera disponible dans vos notifications une fois généré.')
                            ->info()
                            ->send();
                    }),
                Action::make('duplicate')
                    ->label('Dupliquer')
                    ->icon(Heroicon::DocumentDuplicate)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Dupliquer ce sondage')
                    ->modalDescription('Une nouvelle copie inactive sera créée avec toutes les questions associées.')
                    ->modalSubmitActionLabel('Dupliquer')
                    ->action(function (Survey $record): void {
                        $copy = $record->replicate(['slug']);
                        $copy->title = $record->title.' (copie)';
                        $copy->is_active = false;
                        $copy->is_public = false;
                        $copy->save();

                        foreach ($record->questions as $question) {
                            $newQuestion = $question->replicate();
                            $newQuestion->survey_id = $copy->id;
                            $newQuestion->save();
                        }

                        Notification::make()
                            ->title('Sondage dupliqué')
                            ->body("Une copie inactive de « {$record->title} » a été créée.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListSurveys::route('/'),
            'view' => ViewSurvey::route('/{record}'),
            'edit' => Pages\EditSurvey::route('/{record}/edit'),
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
