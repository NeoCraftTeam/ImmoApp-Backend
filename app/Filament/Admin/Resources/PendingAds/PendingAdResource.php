<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PendingAds;

use App\Enums\AdStatus;
use App\Filament\Admin\Resources\PendingAds\Pages\ManagePendingAds;
use App\Filament\Resources\Ads\Concerns\SharedAdResource;
use App\Mail\AdApprovedMail;
use App\Mail\AdDeclinedMail;
use App\Models\Ad;
use App\Services\AiDescriptionEnhancer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use UnitEnum;

class PendingAdResource extends Resource
{
    use SharedAdResource;

    protected static ?string $model = Ad::class;

    protected static string|null|UnitEnum $navigationGroup = 'Annonces';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'À valider';

    protected static ?string $modelLabel = 'Annonce en attente';

    protected static ?string $pluralModelLabel = 'Annonces en attente';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'pending-ads';

    /**
     * Scope to only pending ads.
     */
    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', AdStatus::PENDING)
            ->with(['user', 'ad_type', 'quarter.city', 'media'])
            ->latest();
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components(static::getSharedInfolistSchema(showMeta: true));
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                SpatieMediaLibraryImageColumn::make('images')
                    ->collection('images')
                    ->label('Photo')
                    ->limit(1)
                    ->circular()
                    ->size(40),
                TextColumn::make('title')
                    ->label('Titre')
                    ->limit(50)
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('user.firstname')
                    ->label('Soumis par')
                    ->formatStateUsing(fn ($record) => $record->user->fullname ?? 'Inconnu')
                    ->searchable(['firstname', 'lastname']),
                TextColumn::make('price')
                    ->label('Prix')
                    ->money('XAF')
                    ->sortable(),
                TextColumn::make('ad_type.name')
                    ->label('Type')
                    ->badge()
                    ->color('info'),
                TextColumn::make('quarter.city.name')
                    ->label('Ville')
                    ->sortable(),
                TextColumn::make('surface_area')
                    ->label('Surface')
                    ->suffix(' m²')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Soumis le')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),

                // ── Approuver ──
                Action::make('approve')
                    ->label('Approuver')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-check-circle')
                    ->modalIconColor('success')
                    ->modalHeading('Approuver cette annonce')
                    ->modalDescription(fn (Ad $record) => "L'annonce \"{$record->title}\" sera publiée et un email de confirmation sera envoyé à l'auteur.")
                    ->action(function (Ad $record): void {
                        $record->forceFill(['status' => AdStatus::AVAILABLE])->save();

                        // Send approval email to author
                        if ($record->user) {
                            try {
                                Mail::to($record->user)->send(new AdApprovedMail($record));
                            } catch (\Throwable $e) {
                                Log::error('Failed to send ad approval email: '.$e->getMessage());
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title('Annonce approuvée ✅')
                            ->body("\"{$record->title}\" est maintenant visible. Un email a été envoyé à l'auteur.")
                            ->send();
                    }),

                // ── Décliner ──
                Action::make('decline')
                    ->label('Décliner')
                    ->icon('heroicon-m-x-circle')
                    ->color('warning')
                    ->requiresConfirmation(false)
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalIconColor('warning')
                    ->modalHeading('Motif de refus de l\'annonce')
                    ->modalDescription(fn (Ad $record) => "Rédigez un motif clair et professionnel pour l'auteur de \"".$record->title.'".')
                    ->form([
                        MarkdownEditor::make('reason')
                            ->label('Motif du refus')
                            ->placeholder('Décrivez les raisons du refus : photos insuffisantes, description incomplète, prix incohérent…')
                            ->helperText('L\'auteur recevra ce message mis en forme dans son email.')
                            ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'undo', 'redo'])
                            ->required()
                            ->minLength(20)
                            ->columnSpanFull(),
                        SchemaActions::make([
                            Action::make('enhance_reason_with_ai')
                                ->label('Améliorer avec l\'IA')
                                ->icon(Heroicon::Sparkles)
                                ->color('info')
                                ->size('sm')
                                ->tooltip('Reformule le motif en français professionnel et clair')
                                ->action(function ($get, $set): void {
                                    $reason = (string) ($get('reason') ?? '');

                                    if (empty(trim($reason))) {
                                        Notification::make()
                                            ->title('Motif vide')
                                            ->body('Veuillez d\'abord saisir un motif avant de l\'améliorer avec l\'IA.')
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    $enhanced = app(AiDescriptionEnhancer::class)->enhanceRejectionReason($reason);
                                    $set('reason', $enhanced);

                                    Notification::make()
                                        ->title('Motif amélioré ✨')
                                        ->success()
                                        ->send();
                                }),
                        ])->columnSpanFull(),
                    ])
                    ->action(function (Ad $record, array $data): void {
                        $reason = $data['reason'] ?? '';

                        // Send decline email before deleting
                        if ($record->user) {
                            try {
                                Mail::to($record->user)->send(new AdDeclinedMail($record, $reason));
                            } catch (\Throwable $e) {
                                Log::error('Failed to send ad decline email: '.$e->getMessage());
                            }
                        }

                        $title = $record->title;
                        $record->forceFill(['status' => AdStatus::DECLINED])->save();

                        Notification::make()
                            ->warning()
                            ->title('Annonce déclinée')
                            ->body("\"{$title}\" a été refusée. L'auteur a été notifié par email.")
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Aucune annonce en attente')
            ->emptyStateDescription('Toutes les annonces ont été traitées. 🎉')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->poll('15s');
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManagePendingAds::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Ad::where('status', AdStatus::PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Annonces à valider';
    }
}
