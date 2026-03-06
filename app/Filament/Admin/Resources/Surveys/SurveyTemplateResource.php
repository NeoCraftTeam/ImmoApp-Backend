<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Surveys;

use App\Models\Survey;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SurveyTemplateResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|\UnitEnum $navigationGroup = 'Utilisateurs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Modèles de questions';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Modèle';

    protected static ?string $pluralModelLabel = 'Modèles de questions';

    protected static ?int $navigationSort = 6;

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('questions');
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Questions du sondage')
                    ->icon(Heroicon::QuestionMarkCircle)
                    ->description('Ajoutez, modifiez ou réordonnez les questions de ce sondage.')
                    ->schema([
                        Repeater::make('questions')
                            ->relationship('questions')
                            ->label('')
                            ->orderColumn('order')
                            ->addActionLabel('Ajouter une question')
                            ->collapsible()
                            ->columns(2)
                            ->schema([
                                TextInput::make('text')
                                    ->label('Question')
                                    ->required()
                                    ->columnSpanFull(),
                                Select::make('type')
                                    ->label('Type')
                                    ->required()
                                    ->options([
                                        'multiple_choice' => 'Choix unique',
                                        'checkbox' => 'Cases à cocher (plusieurs choix)',
                                        'rating' => 'Note (1–5 étoiles)',
                                        'text' => 'Texte libre',
                                    ])
                                    ->live(),
                                TagsInput::make('options')
                                    ->label('Options')
                                    ->placeholder('Appuyez sur Entrée pour ajouter')
                                    ->helperText('Requis pour choix unique et cases à cocher')
                                    ->visible(fn ($get) => in_array($get('type'), ['multiple_choice', 'checkbox'])),
                            ]),
                    ]),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->heading('Modèles de questions')
            ->description('Gérez les questions de chaque sondage indépendamment.')
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Sondage')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Survey $record): string => $record->description ?? ''),
                TextColumn::make('questions_count')
                    ->label('Questions')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Gérer les questions')
                    ->icon(Heroicon::PencilSquare),
            ])
            ->toolbarActions([]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveyTemplates::route('/'),
            'edit' => Pages\EditSurveyTemplate::route('/{record}/edit'),
        ];
    }
}
