<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ActivityLogs;

use App\Enums\AdminPermission;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\ActivityLogs\Pages\ManageActivityLogs;
use App\Models\User;
use App\Support\AuditDescription;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|null|UnitEnum $navigationGroup = 'Audit';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Journal de Sécurité';

    protected static ?string $modelLabel = 'Activité';

    protected static ?string $pluralModelLabel = 'Journal de Sécurité';

    protected static ?int $navigationSort = 3;

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminPermission::ActivityLogsView) ?? false;
    }

    /**
     * Scope activity log to admin-only actions.
     */
    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('causer_type', User::class)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', 'activity_log.causer_id')
                    ->where('users.role', UserRole::ADMIN);
            })
            ->with(['causer', 'subject']);
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
                        $isSecurityLog = $record->log_name === 'security';
                        $props = $record->properties->toArray();

                        $adminName = $causer ? "{$causer->firstname} {$causer->lastname}" : 'Système';
                        $adminEmail = $causer?->email ?? ''; // @phpstan-ignore nullsafe.neverNull
                        $entity = AuditDescription::entityLabel($record);

                        $action = $props['action'] ?? $record->event ?? 'unknown';
                        $event = AuditDescription::actionLabel($record);

                        [$eventColor, $eventBg] = $isSecurityLog
                            ? match ($action) {
                                'login' => ['#0369a1', '#e0f2fe'],
                                'logout' => ['#64748b', '#f1f5f9'],
                                'login_failed', 'lockout' => ['#dc2626', '#fee2e2'],
                                'password_reset' => ['#7c3aed', '#ede9fe'],
                                default => ['#64748b', '#f1f5f9'],
                            }
                        : match ($record->event) {
                            'created' => ['#166534', '#dcfce7'],
                            'updated' => ['#92400e', '#fef3c7'],
                            'deleted' => ['#991b1b', '#fee2e2'],
                            default => ['#64748b', '#f1f5f9'],
                        };

                        $accentBorder = $isSecurityLog ? '#F6475F' : match ($record->event) {
                            'created' => '#22c55e',
                            'updated' => '#f59e0b',
                            'deleted' => '#ef4444',
                            default => '#94a3b8',
                        };

                        $logBadgeLabel = $isSecurityLog ? 'Sécurité' : 'Action Admin';
                        $logBadgeBg = $isSecurityLog ? '#fef2f2' : '#eff6ff';
                        $logBadgeColor = $isSecurityLog ? '#F6475F' : '#1d4ed8';
                        $logBadgeBorder = $isSecurityLog ? '#fecdd3' : '#bfdbfe';

                        $date = $record->created_at->format('d/m/Y à H:i:s');
                        $description = AuditDescription::forActivity($record);

                        $ip = $props['ip'] ?? null;
                        $ua = $props['user_agent'] ?? null;
                        $guard = $props['guard'] ?? null;

                        $uaShort = $ua ? (mb_strlen($ua) > 72 ? mb_substr($ua, 0, 72).'…' : $ua) : null;

                        return json_encode(compact(
                            'adminName', 'adminEmail', 'entity', 'event', 'eventColor', 'eventBg',
                            'accentBorder', 'logBadgeLabel', 'logBadgeBg', 'logBadgeColor', 'logBadgeBorder',
                            'date', 'description', 'isSecurityLog', 'ip', 'uaShort', 'guard', 'action'
                        ), JSON_UNESCAPED_UNICODE);
                    })
                    ->formatStateUsing(function (string $state): string {
                        $d = json_decode($state, true);

                        $pill = fn (string $label, string $value, string $bg, string $color, string $border = 'transparent'): string => '<div style="display:flex;align-items:center;gap:6px;">'
                            .'<span style="font-size:10px;color:#94a3b8;text-transform:uppercase;font-weight:700;letter-spacing:0.06em;white-space:nowrap;">'.e($label).'</span>'
                            .'<span style="display:inline-block;padding:2px 10px;border-radius:9999px;font-size:12px;font-weight:600;background:'.e($bg).';color:'.e($color).';border:1px solid '.e($border).';">'.e($value).'</span>'
                            .'</div>';

                        $metaText = fn (string $label, string $value): string => '<div style="display:flex;align-items:baseline;gap:6px;">'
                            .'<span style="font-size:10px;color:#94a3b8;text-transform:uppercase;font-weight:700;letter-spacing:0.06em;white-space:nowrap;">'.e($label).'</span>'
                            .'<span style="font-size:12px;color:#334155;font-weight:500;">'.e($value).'</span>'
                            .'</div>';

                        $html = '<div style="border-left:4px solid '.e($d['accentBorder']).';background:#f8fafc;border-radius:0 12px 12px 0;padding:18px 22px;border:1px solid #e2e8f0;border-left-width:4px;">';

                        $html .= '<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;">';
                        $html .= '<span style="display:inline-block;padding:3px 12px;border-radius:9999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;background:'.e($d['logBadgeBg']).';color:'.e($d['logBadgeColor']).';border:1px solid '.e($d['logBadgeBorder']).';">';
                        $html .= e($d['logBadgeLabel']);
                        $html .= '</span>';
                        $html .= '<span style="font-size:12px;color:#94a3b8;">'.e($d['date']).'</span>';
                        $html .= '</div>';

                        $html .= '<div style="font-size:15px;color:#0f172a;font-weight:600;margin-bottom:16px;line-height:1.6;">'.e($d['description']).'</div>';

                        $html .= '<div style="display:flex;flex-wrap:wrap;gap:12px 24px;">';
                        $html .= $pill('Action', $d['event'], $d['eventBg'], $d['eventColor']);
                        if ($d['entity'] !== '—') {
                            $html .= $pill('Entité', $d['entity'], '#dbeafe', '#1d4ed8', '#bfdbfe');
                        }
                        $html .= $metaText('Admin', $d['adminName'].($d['adminEmail'] ? ' · '.$d['adminEmail'] : ''));
                        $html .= '</div>';

                        if ($d['isSecurityLog'] && ($d['ip'] || $d['uaShort'] || $d['guard'])) {
                            $html .= '<div style="margin-top:14px;padding:14px 18px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;">';
                            $html .= '<div style="font-size:10px;font-weight:700;color:#c2410c;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:10px;">Informations réseau</div>';
                            $html .= '<div style="display:flex;flex-wrap:wrap;gap:10px 28px;">';
                            if ($d['ip']) {
                                $html .= $metaText('Adresse IP', $d['ip']);
                            }
                            if ($d['guard']) {
                                $html .= $metaText('Guard', $d['guard']);
                            }
                            if ($d['uaShort']) {
                                $html .= $metaText('Navigateur', $d['uaShort']);
                            }
                            $html .= '</div></div>';
                        }

                        $html .= '</div>';

                        return $html;
                    })
                    ->html(),

                TextEntry::make('changes_diff')
                    ->label('')
                    ->columnSpanFull()
                    ->getStateUsing(fn ($record): string => json_encode([
                        'old' => $record->properties->get('old') ?? [],
                        'attributes' => $record->properties->get('attributes') ?? [],
                        'event' => $record->event,
                    ], JSON_UNESCAPED_UNICODE))
                    ->formatStateUsing(function (string $state): string {
                        $data = json_decode($state, true);

                        return self::renderDiffTable($data['old'] ?? [], $data['attributes'] ?? [], $data['event'] ?? null);
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
                TextColumn::make('log_name')
                    ->label('Journal')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'security' => 'Sécurité',
                        'default' => 'Admin',
                        default => ucfirst($state ?? '?'),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'security' => 'danger',
                        'default' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): string => match ($state) {
                        'security' => 'heroicon-o-shield-exclamation',
                        default => 'heroicon-o-cog-6-tooth',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-clock')
                    ->size('sm'),
                TextColumn::make('action_summary')
                    ->label('Action')
                    ->wrap()
                    ->getStateUsing(fn ($record): string => AuditDescription::forActivity($record))
                    ->searchable(query: fn ($query, string $search) => $query->where('description', 'ilike', "%{$search}%"))
                    ->tooltip(fn ($record): string => AuditDescription::forActivity($record)),
                TextColumn::make('subject_type')
                    ->label('Entité')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($record): string => AuditDescription::entityLabel($record))
                    ->sortable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->icon('heroicon-o-globe-alt')
                    ->size('sm')
                    ->getStateUsing(fn ($record): string => (string) ($record->properties->get('ip') ?? '—'))
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Journal')
                    ->options([
                        'security' => 'Sécurité',
                        'default' => 'Actions Admin',
                        'settings' => 'Paramètres',
                    ])
                    ->native(false),
                SelectFilter::make('event')
                    ->label('Type')
                    ->options([
                        'created' => 'Création',
                        'updated' => 'Modification',
                        'deleted' => 'Suppression',
                    ])
                    ->native(false),
                SelectFilter::make('subject_type')
                    ->label('Entité')
                    ->options(AuditDescription::ENTITY_LABELS)
                    ->native(false)
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalIcon(function ($record): string {
                        if ($record->log_name === 'security') {
                            return match ($record->properties->get('action')) {
                                'login' => 'heroicon-o-arrow-right-on-rectangle',
                                'logout' => 'heroicon-o-arrow-left-on-rectangle',
                                'login_failed', 'lockout' => 'heroicon-o-exclamation-triangle',
                                'password_reset' => 'heroicon-o-key',
                                default => 'heroicon-o-shield-check',
                            };
                        }

                        return match ($record->event) {
                            'created' => 'heroicon-o-plus-circle',
                            'updated' => 'heroicon-o-pencil-square',
                            'deleted' => 'heroicon-o-trash',
                            default => 'heroicon-o-shield-check',
                        };
                    })
                    ->modalIconColor(function ($record): string {
                        if ($record->log_name === 'security') {
                            return match ($record->properties->get('action')) {
                                'login' => 'info',
                                'login_failed', 'lockout' => 'danger',
                                'password_reset' => 'warning',
                                default => 'gray',
                            };
                        }

                        return match ($record->event) {
                            'created' => 'success',
                            'updated' => 'warning',
                            'deleted' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->modalHeading(function ($record): string {
                        if ($record->log_name === 'security') {
                            return match ($record->properties->get('action')) {
                                'login' => 'Connexion administrateur',
                                'logout' => 'Déconnexion administrateur',
                                'login_failed' => 'Tentative de connexion échouée',
                                'lockout' => 'Compte verrouillé',
                                'password_reset' => 'Réinitialisation du mot de passe',
                                default => 'Événement de sécurité',
                            };
                        }

                        return match ($record->event) {
                            'created' => 'Création d\'un enregistrement',
                            'updated' => 'Modification d\'un enregistrement',
                            'deleted' => 'Suppression d\'un enregistrement',
                            default => 'Activité de journal',
                        };
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

    /**
     * Render a combined diff table showing old → new values side by side.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private static function renderDiffTable(array $old, array $new, ?string $event = null): string
    {
        $ignoredKeys = ['updated_at', 'created_at', 'id'];
        $sensitiveKeys = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'two_factor_secret', 'two_factor_recovery_codes', 'api_token', 'stripe_id', 'pm_last_four'];
        $old = array_diff_key($old, array_flip($ignoredKeys), array_flip($sensitiveKeys));
        $new = array_diff_key($new, array_flip($ignoredKeys), array_flip($sensitiveKeys));

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        if (empty($allKeys)) {
            return '<div style="padding:12px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;color:#94a3b8;font-style:italic;font-size:13px;">Aucune modification enregistrée.</div>';
        }

        $accentColor = match ($event) {
            'created' => '#22c55e',
            'updated' => '#f59e0b',
            'deleted' => '#ef4444',
            default => '#F6475F',
        };

        $rows = '';
        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            $isOnlyNew = is_null($oldVal) && !is_null($newVal);
            $isOnlyOld = !is_null($oldVal) && is_null($newVal);

            $oldDisplay = self::formatCellValue($oldVal);
            $newDisplay = self::formatCellValue($newVal);

            $oldCellBg = $isOnlyNew ? '#f8fafc' : '#fef2f2';
            $newCellBg = $isOnlyOld ? '#f8fafc' : '#f0fdf4';
            $oldTextColor = $isOnlyNew ? '#94a3b8' : '#991b1b';
            $newTextColor = $isOnlyOld ? '#94a3b8' : '#166534';

            $rows .= '<tr>'
                .'<td style="padding:9px 14px;font-size:12px;font-weight:600;color:#475569;border-bottom:1px solid #f1f5f9;vertical-align:top;white-space:nowrap;">'.e(self::humanizeFieldName($key)).'</td>'
                .'<td style="padding:9px 14px;font-size:12px;color:'.$oldTextColor.';background:'.$oldCellBg.';border-bottom:1px solid #f1f5f9;word-break:break-all;vertical-align:top;">'.$oldDisplay.'</td>'
                .'<td style="padding:9px 6px;font-size:14px;color:#cbd5e1;border-bottom:1px solid #f1f5f9;text-align:center;vertical-align:top;width:28px;">&#8594;</td>'
                .'<td style="padding:9px 14px;font-size:12px;color:'.$newTextColor.';background:'.$newCellBg.';border-bottom:1px solid #f1f5f9;word-break:break-all;vertical-align:top;">'.$newDisplay.'</td>'
                .'</tr>';
        }

        $countLabel = count($allKeys).' champ'.(count($allKeys) > 1 ? 's' : '').' modifié'.(count($allKeys) > 1 ? 's' : '');

        return '<div style="margin-top:4px;">'
            .'<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">'
            .'<div style="height:3px;width:24px;background:'.$accentColor.';border-radius:2px;"></div>'
            .'<span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.07em;">Modifications détaillées</span>'
            .'<span style="font-size:11px;color:#94a3b8;">— '.$countLabel.'</span>'
            .'</div>'
            .'<div style="overflow-x:auto;border-radius:10px;border:1px solid #e2e8f0;">'
            .'<table style="width:100%;border-collapse:collapse;table-layout:fixed;min-width:380px;">'
            .'<colgroup><col style="width:20%;"><col style="width:36%;"><col style="width:5%;"><col style="width:39%;"></colgroup>'
            .'<thead><tr style="background:#f8fafc;">'
            .'<th style="padding:9px 14px;font-size:10px;font-weight:700;color:#64748b;text-align:left;border-bottom:2px solid '.e($accentColor).';text-transform:uppercase;letter-spacing:0.06em;">Champ</th>'
            .'<th style="padding:9px 14px;font-size:10px;font-weight:700;color:#991b1b;text-align:left;border-bottom:2px solid '.e($accentColor).';text-transform:uppercase;letter-spacing:0.06em;">Avant</th>'
            .'<th style="padding:9px 4px;border-bottom:2px solid '.e($accentColor).';"></th>'
            .'<th style="padding:9px 14px;font-size:10px;font-weight:700;color:#166534;text-align:left;border-bottom:2px solid '.e($accentColor).';text-transform:uppercase;letter-spacing:0.06em;">Après</th>'
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table></div></div>';
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
            'description' => 'Description',
            'body' => 'Contenu',
            'content' => 'Contenu',
            'badge' => 'Badge',
            'price' => 'Prix',
            'amount' => 'Montant',
            'points' => 'Crédits',
            'points_awarded' => 'Crédits octroyés',
            'bonus_points' => 'Bonus crédits',
            'is_active' => 'Actif',
            'is_verified' => 'Vérifié',
            'is_featured' => 'En vedette',
            'is_published' => 'Publié',
            'sort_order' => 'Ordre',
            'slug' => 'Slug',
            'icon' => 'Icône',
            'color' => 'Couleur',
            'email' => 'Email',
            'firstname' => 'Prénom',
            'lastname' => 'Nom de famille',
            'phone' => 'Téléphone',
            'role' => 'Rôle',
            'status' => 'Statut',
            'title' => 'Titre',
            'type' => 'Type',
            'rating' => 'Note',
            'comment' => 'Commentaire',
            'address' => 'Adresse',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
            'lat' => 'Latitude',
            'lng' => 'Longitude',
            'zip_code' => 'Code postal',
            'country' => 'Pays',
            'city_id' => 'Ville',
            'quarter_id' => 'Quartier',
            'ad_id' => 'Annonce',
            'user_id' => 'Utilisateur',
            'agency_id' => 'Agence',
            'plan_id' => 'Plan',
            'expires_at' => 'Expire le',
            'starts_at' => 'Commence le',
            'ends_at' => 'Termine le',
            'verified_at' => 'Vérifié le',
            'published_at' => 'Publié le',
            'deleted_at' => 'Supprimé le',
            'unlock_price' => 'Prix déblocage',
            'unlock_cost_points' => 'Coût déblocage (crédits)',
            'welcome_bonus_points' => 'Bonus bienvenue',
            'ad_lifetime_days' => 'Durée annonce (jours)',
            'max_ads' => 'Nb annonces max',
            'monthly_price' => 'Prix mensuel',
            'annual_price' => 'Prix annuel',
            'trial_days' => "Jours d'essai",
            'auto_renew' => 'Renouvellement auto',
            'gateway' => 'Passerelle',
            'transaction_id' => 'ID transaction',
            'payment_method' => 'Méthode paiement',
        ];

        return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
    }
}
