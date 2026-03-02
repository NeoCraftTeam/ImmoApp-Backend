<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ActivityLogs;

use App\Filament\Admin\Resources\ActivityLogs\Pages\ManageActivityLogs;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|null|UnitEnum $navigationGroup = 'Administration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Journal de Sécurité';

    protected static ?string $modelLabel = 'Activité';

    protected static ?string $pluralModelLabel = 'Journal de Sécurité';

    protected static ?int $navigationSort = 3;

    /**
     * @var array<string, string>
     */
    private const array ENTITY_LABELS = [
        \App\Models\Ad::class => 'Annonce',
        \App\Models\User::class => 'Utilisateur',
        \App\Models\Agency::class => 'Agence',
        \App\Models\City::class => 'Ville',
        \App\Models\Quarter::class => 'Quartier',
        \App\Models\AdType::class => "Type d'annonce",
        \App\Models\Review::class => 'Avis',
        \App\Models\Payment::class => 'Paiement',
        \App\Models\Subscription::class => 'Abonnement',
        \App\Models\SubscriptionPlan::class => "Plan d'abonnement",
        \App\Models\PointPackage::class => 'Pack de crédits',
        \App\Models\UnlockedAd::class => 'Déblocage',
        \App\Models\PropertyAttribute::class => 'Attribut',
        \App\Models\Setting::class => 'Paramètre',
    ];

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Scope activity log to admin-only actions.
     */
    #[\Override]
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('causer_type', \App\Models\User::class)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', 'activity_log.causer_id')
                    ->where('users.role', \App\Enums\UserRole::ADMIN);
            })
            ->with('causer');
    }

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('activity_summary')
                    ->label('')
                    ->columnSpanFull()
                    ->getStateUsing(function ($record): string {
                        $causer = $record->causer;
                        $adminName = $causer ? "{$causer->firstname} {$causer->lastname}" : 'Système';
                        $adminEmail = $causer?->email ?? ''; // @phpstan-ignore nullsafe.neverNull
                        $entity = self::ENTITY_LABELS[$record->subject_type] ?? ($record->subject_type ? class_basename($record->subject_type) : '—');
                        $event = match ($record->event) {
                            'created' => 'Création',
                            'updated' => 'Modification',
                            'deleted' => 'Suppression',
                            default => ucfirst($record->event ?? '—'),
                        };
                        $eventColor = match ($record->event) {
                            'created' => '#16a34a',
                            'updated' => '#d97706',
                            'deleted' => '#dc2626',
                            default => '#64748b',
                        };
                        $eventBg = match ($record->event) {
                            'created' => '#dcfce7',
                            'updated' => '#fef3c7',
                            'deleted' => '#fee2e2',
                            default => '#f1f5f9',
                        };
                        $date = $record->created_at->format('d/m/Y à H:i:s');
                        $description = $record->description ?? '—';

                        return json_encode(compact('adminName', 'adminEmail', 'entity', 'event', 'eventColor', 'eventBg', 'date', 'description'), JSON_UNESCAPED_UNICODE);
                    })
                    ->formatStateUsing(function (string $state): string {
                        $d = json_decode($state, true);

                        return '
                        <div style="padding: 16px 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                            <div style="font-size: 15px; color: #1e293b; font-weight: 500; margin-bottom: 16px; line-height: 1.5;">'.e($d['description']).'</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 16px 32px;">
                                <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                    <span style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; flex-shrink: 0;">Action</span>
                                    <span style="display: inline-block; padding: 3px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; background: '.$d['eventBg'].'; color: '.$d['eventColor'].';">'.e($d['event']).'</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                    <span style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; flex-shrink: 0;">Entité</span>
                                    <span style="display: inline-block; padding: 3px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; background: #dbeafe; color: #1d4ed8;">'.e($d['entity']).'</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                    <span style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; flex-shrink: 0;">Admin</span>
                                    <span style="font-size: 13px; color: #334155; font-weight: 500;">'.e($d['adminName']).'</span>
                                    <span style="font-size: 12px; color: #94a3b8;">'.e($d['adminEmail']).'</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                    <span style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; flex-shrink: 0;">Date</span>
                                    <span style="font-size: 13px; color: #334155;">'.e($d['date']).'</span>
                                </div>
                            </div>
                        </div>';
                    })
                    ->html(),

                TextEntry::make('changes_diff')
                    ->label('')
                    ->columnSpanFull()
                    ->getStateUsing(fn ($record): string => json_encode([
                        'old' => $record->properties->get('old') ?? [],
                        'attributes' => $record->properties->get('attributes') ?? [],
                    ], JSON_UNESCAPED_UNICODE))
                    ->formatStateUsing(function (string $state): string {
                        $data = json_decode($state, true);

                        return self::renderDiffTable($data['old'] ?? [], $data['attributes'] ?? []);
                    })
                    ->html()
                    ->visible(fn ($record): bool => !empty($record->properties->get('old')) || !empty($record->properties->get('attributes'))),
            ]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->striped()
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-clock')
                    ->size('sm'),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(70)
                    ->searchable()
                    ->wrap()
                    ->tooltip(fn ($record) => $record->description),
                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'created' => 'Création',
                        'updated' => 'Modification',
                        'deleted' => 'Suppression',
                        default => ucfirst($state ?? '-'),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('Entité')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => self::ENTITY_LABELS[$state] ?? ($state ? class_basename($state) : '-'))
                    ->sortable(),
                TextColumn::make('causer.firstname')
                    ->label('Admin')
                    ->icon('heroicon-o-user-circle')
                    ->formatStateUsing(function ($record): string {
                        $causer = $record->causer;
                        if (!$causer) {
                            return 'Système';
                        }

                        return "{$causer->firstname} {$causer->lastname}";
                    })
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Action')
                    ->options([
                        'created' => 'Création',
                        'updated' => 'Modification',
                        'deleted' => 'Suppression',
                    ])
                    ->native(false),
                SelectFilter::make('subject_type')
                    ->label('Entité')
                    ->options(self::ENTITY_LABELS)
                    ->native(false)
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalWidth('3xl')
                    ->modalHeading(fn ($record) => match ($record->event) {
                        'created' => '🟢  Création',
                        'updated' => '🟠  Modification',
                        'deleted' => '🔴  Suppression',
                        default => 'Activité',
                    }),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageActivityLogs::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereDate('created_at', today())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    #[\Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Activités admin aujourd\'hui';
    }

    /**
     * Render a combined diff table showing old → new values side by side.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private static function renderDiffTable(array $old, array $new): string
    {
        $ignoredKeys = ['updated_at', 'created_at', 'id'];
        $old = array_diff_key($old, array_flip($ignoredKeys));
        $new = array_diff_key($new, array_flip($ignoredKeys));

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        if (empty($allKeys)) {
            return '<p style="color: #94a3b8; font-style: italic;">Aucune modification significative</p>';
        }

        $rows = '';
        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            $oldDisplay = self::formatCellValue($oldVal);
            $newDisplay = self::formatCellValue($newVal);

            $rows .= '<tr>
                <td style="padding: 10px 14px; font-size: 13px; font-weight: 600; color: #475569; border-bottom: 1px solid #f1f5f9; vertical-align: top; word-break: break-word;">'.e(self::humanizeFieldName($key)).'</td>
                <td style="padding: 10px 14px; font-size: 13px; color: #dc2626; background-color: #fef2f2; border-bottom: 1px solid #f1f5f9; word-break: break-word; vertical-align: top;">'.$oldDisplay.'</td>
                <td style="padding: 10px 4px; font-size: 16px; color: #94a3b8; border-bottom: 1px solid #f1f5f9; text-align: center; vertical-align: top; width: 30px;">→</td>
                <td style="padding: 10px 14px; font-size: 13px; color: #15803d; background-color: #f0fdf4; border-bottom: 1px solid #f1f5f9; word-break: break-word; vertical-align: top;">'.$newDisplay.'</td>
            </tr>';
        }

        return '<div style="overflow-x: auto; border-radius: 10px; border: 1px solid #e2e8f0;">
            <table style="width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 400px;">
                <colgroup>
                    <col style="width: 22%;">
                    <col style="width: 35%;">
                    <col style="width: 6%;">
                    <col style="width: 37%;">
                </colgroup>
                <thead>
                    <tr style="background-color: #f8fafc;">
                        <th style="padding: 10px 14px; font-size: 11px; font-weight: 700; color: #64748b; text-align: left; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.05em;">Champ</th>
                        <th style="padding: 10px 14px; font-size: 11px; font-weight: 700; color: #dc2626; text-align: left; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.05em;">Avant</th>
                        <th style="padding: 10px 4px; font-size: 11px; border-bottom: 2px solid #e2e8f0;"></th>
                        <th style="padding: 10px 14px; font-size: 11px; font-weight: 700; color: #15803d; text-align: left; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.05em;">Après</th>
                    </tr>
                </thead>
                <tbody>'.$rows.'</tbody>
            </table>
        </div>';
    }

    /**
     * Format a single cell value for the diff table.
     */
    private static function formatCellValue(mixed $value): string
    {
        if (is_null($value) || $value === '') {
            return '<span style="color: #94a3b8; font-style: italic;">—</span>';
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        if (is_array($value)) {
            return e(json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return e((string) $value);
    }

    /**
     * Humanize a snake_case field name.
     */
    private static function humanizeFieldName(string $key): string
    {
        $labels = [
            'name' => 'Nom',
            'desc' => 'Description',
            'badge' => 'Badge',
            'price' => 'Prix',
            'points' => 'Crédits',
            'is_active' => 'Actif',
            'sort_order' => 'Ordre',
            'slug' => 'Slug',
            'icon' => 'Icône',
            'email' => 'Email',
            'firstname' => 'Prénom',
            'lastname' => 'Nom',
            'phone' => 'Téléphone',
            'role' => 'Rôle',
            'status' => 'Statut',
            'title' => 'Titre',
            'rating' => 'Note',
            'comment' => 'Commentaire',
            'city_id' => 'Ville',
            'ad_id' => 'Annonce',
            'user_id' => 'Utilisateur',
            'unlock_price' => 'Prix de déblocage',
            'unlock_cost_points' => 'Coût déblocage (crédits)',
            'welcome_bonus_points' => 'Bonus bienvenue',
            'ad_lifetime_days' => 'Durée de vie annonce',
        ];

        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
