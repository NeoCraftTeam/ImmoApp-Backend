<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\NewsletterCampaigns;

use App\Enums\AdminPermission;
use App\Filament\Admin\Resources\NewsletterCampaigns\Pages\ManageNewsletterCampaigns;
use App\Jobs\SendNewsletterCampaignJob;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Services\Ai\AiDescriptionEnhancer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class NewsletterCampaignResource extends Resource
{
    protected static ?string $model = NewsletterCampaign::class;

    protected static string|null|\UnitEnum $navigationGroup = 'Marketing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Megaphone;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Campagnes Newsletter';

    protected static ?string $modelLabel = 'Campagne';

    protected static ?string $pluralModelLabel = 'Campagnes Newsletter';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static bool $isScopedToTenant = false;

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyAdminPermission([
            AdminPermission::NewsletterView,
            AdminPermission::NewsletterSend,
        ]) ?? false;
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::NewsletterSend) ?? false;
    }

    #[\Override]
    public static function canEdit($record): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::NewsletterSend) ?? false;
    }

    #[\Override]
    public static function canDelete($record): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::NewsletterSend) ?? false;
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contenu de la campagne')
                    ->icon(Heroicon::PencilSquare)
                    ->columns(1)
                    ->schema([
                        TextInput::make('subject')
                            ->label('Objet')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Envelope)
                            ->placeholder('Ex: Les meilleures offres de la semaine')
                            ->columnSpanFull(),
                        RichEditor::make('body')
                            ->label('Contenu')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'orderedList',
                                'bulletList',
                                'h2',
                                'h3',
                                'blockquote',
                                'redo',
                                'undo',
                            ]),
                        SchemaActions::make([
                            Action::make('enhance_with_ai')
                                ->label('Améliorer avec l\'IA')
                                ->icon(Heroicon::Sparkles)
                                ->color('info')
                                ->size('sm')
                                ->tooltip('Utilisez l\'IA pour améliorer le contenu de votre campagne')
                                ->action(function ($get, $set): void {
                                    $body = (string) ($get('body') ?? '');

                                    if (empty(trim(strip_tags($body)))) {
                                        Notification::make()
                                            ->title('Contenu vide')
                                            ->body('Veuillez d\'abord rédiger du contenu avant de l\'améliorer avec l\'IA.')
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    $enhanced = app(AiDescriptionEnhancer::class)->enhanceNewsletter($body);
                                    $set('body', $enhanced);

                                    Notification::make()
                                        ->title('Contenu amélioré ✨')
                                        ->success()
                                        ->send();
                                }),
                        ])->columnSpanFull(),
                    ]),
            ])
            ->columns(1);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')
                    ->label('Objet')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('creator.firstname')
                    ->label('Créé par')
                    ->sortable()
                    ->placeholder('Système'),
                TextColumn::make('recipients_count')
                    ->label('Destinataires')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                IconColumn::make('is_sent')
                    ->label('Envoyé')
                    ->state(fn (NewsletterCampaign $record): bool => $record->isSent())
                    ->boolean()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        'CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END '.$direction
                    )),
                TextColumn::make('sent_at')
                    ->label('Envoyé le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable()
                    ->placeholder('Brouillon'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y à H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->filters([
                TernaryFilter::make('is_sent')
                    ->label('Envoyé')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('sent_at'),
                        false: fn (Builder $query) => $query->whereNull('sent_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->successNotificationTitle('Campagne mise à jour')
                    ->visible(fn (NewsletterCampaign $record): bool => !$record->isSent()),
                Action::make('send')
                    ->label('Envoyer')
                    ->icon(Heroicon::PaperAirplane)
                    ->color('success')
                    ->visible(fn (NewsletterCampaign $record): bool => !$record->isSent())
                    ->requiresConfirmation()
                    ->modalHeading('Envoyer cette campagne ?')
                    ->modalDescription(function (): string {
                        $count = NewsletterSubscriber::query()
                            ->whereNotNull('confirmed_at')
                            ->whereNull('unsubscribed_at')
                            ->count();

                        return "Cette campagne sera envoyée à {$count} abonné(s) actif(s). Cette action est irréversible.";
                    })
                    ->modalSubmitActionLabel('Envoyer maintenant')
                    ->action(function (NewsletterCampaign $record): void {
                        $record->update(['created_by' => auth()->id()]);

                        SendNewsletterCampaignJob::dispatch($record);

                        Notification::make()
                            ->title('Campagne en cours d\'envoi')
                            ->body('Les emails sont en cours de distribution aux abonnés.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->successNotificationTitle('Campagne supprimée')
                    ->visible(fn (NewsletterCampaign $record): bool => !$record->isSent()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->successNotificationTitle('Campagnes supprimées'),
                ]),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageNewsletterCampaigns::route('/'),
        ];
    }
}
