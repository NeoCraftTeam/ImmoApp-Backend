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

                        // Per-event pill classes — listed explicitly so
                        // Tailwind's JIT picks them up at build time (dynamic
                        // class names cannot be detected). Each tuple is
                        // [background+text, border]; both halves include
                        // their dark-mode counterparts.
                        $eventPillClass = $isSecurityLog
                            ? match ($action) {
                                'login' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200',
                                'logout' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                'login_failed', 'lockout' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200',
                                'password_reset' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200',
                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                            }
                        : match ($record->event) {
                            'created' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                            'updated' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                            'deleted' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
                            default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                        };

                        // Left accent strip — also class-based for dark mode parity.
                        $accentBorderClass = $isSecurityLog
                            ? 'border-l-rose-500 dark:border-l-rose-400'
                            : match ($record->event) {
                                'created' => 'border-l-emerald-500 dark:border-l-emerald-400',
                                'updated' => 'border-l-amber-500 dark:border-l-amber-400',
                                'deleted' => 'border-l-red-500 dark:border-l-red-400',
                                default => 'border-l-slate-400 dark:border-l-slate-500',
                            };

                        $logBadgeLabel = $isSecurityLog ? 'Sécurité' : 'Action Admin';
                        $logBadgeClass = $isSecurityLog
                            ? 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-900/30 dark:text-rose-200 dark:ring-rose-700/50'
                            : 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-900/30 dark:text-blue-200 dark:ring-blue-700/50';

                        $date = $record->created_at->format('d/m/Y à H:i:s').' UTC';
                        $description = AuditDescription::forActivity($record);

                        // L'adresse IP est volontairement ignorée (retirée du
                        // journal — minimisation des données personnelles).
                        $ua = $props['user_agent'] ?? null;
                        $guard = $props['guard'] ?? null;

                        $uaShort = $ua ? (mb_strlen($ua) > 72 ? mb_substr($ua, 0, 72).'…' : $ua) : null;

                        return json_encode(compact(
                            'adminName', 'adminEmail', 'entity', 'event', 'eventPillClass',
                            'accentBorderClass', 'logBadgeLabel', 'logBadgeClass',
                            'date', 'description', 'isSecurityLog', 'uaShort', 'guard', 'action'
                        ), JSON_UNESCAPED_UNICODE);
                    })
                    ->formatStateUsing(function (string $state): string {
                        $d = json_decode($state, true);

                        // Pill helper — `$pillClasses` is a pre-validated
                        // Tailwind class string (see $eventPillClass match
                        // in getStateUsing) so the JIT compiles each variant.
                        $pill = fn (string $label, string $value, string $pillClasses): string => '<div class="flex items-center gap-1.5">'
                            .'<span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500 whitespace-nowrap">'.e($label).'</span>'
                            .'<span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset '.$pillClasses.'">'.e($value).'</span>'
                            .'</div>';

                        $metaText = fn (string $label, string $value): string => '<div class="flex items-baseline gap-1.5">'
                            .'<span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500 whitespace-nowrap">'.e($label).'</span>'
                            .'<span class="text-xs font-medium text-slate-700 dark:text-slate-300">'.e($value).'</span>'
                            .'</div>';

                        // Outer card — borders + bg are now class-based with
                        // dark-mode counterparts so the activity-log
                        // slide-over stays legible in either theme.
                        $html = '<div class="border-l-4 '.$d['accentBorderClass'].' bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-r-xl p-[18px_22px]">';

                        $html .= '<div class="flex items-center gap-2.5 mb-3.5 flex-wrap">';
                        $html .= '<span class="inline-block px-3 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ring-1 ring-inset '.$d['logBadgeClass'].'">';
                        $html .= e($d['logBadgeLabel']);
                        $html .= '</span>';
                        $html .= '<span class="text-xs text-slate-400 dark:text-slate-500">'.e($d['date']).'</span>';
                        $html .= '</div>';

                        $html .= '<div class="text-[15px] font-semibold text-slate-900 dark:text-slate-100 mb-4 leading-relaxed">'.e($d['description']).'</div>';

                        $html .= '<div class="flex flex-wrap gap-x-6 gap-y-3">';
                        $html .= $pill('Action', $d['event'], $d['eventPillClass']);
                        if ($d['entity'] !== '—') {
                            $html .= $pill('Entité', $d['entity'], 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-900/40 dark:text-blue-200 dark:ring-blue-700/50');
                        }
                        $html .= $metaText('Admin', $d['adminName'].($d['adminEmail'] ? ' · '.$d['adminEmail'] : ''));
                        $html .= '</div>';

                        if ($d['isSecurityLog'] && ($d['uaShort'] || $d['guard'])) {
                            $html .= '<div class="mt-3.5 p-[14px_18px] bg-orange-50 dark:bg-orange-950/30 border border-orange-200 dark:border-orange-800/60 rounded-lg">';
                            $html .= '<div class="text-[10px] font-bold uppercase tracking-wider text-orange-700 dark:text-orange-300 mb-2.5">Session</div>';
                            $html .= '<div class="flex flex-wrap gap-x-7 gap-y-2.5">';
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
                    ->label('Date (UTC)')
                    ->dateTime('d/m/Y H:i', 'UTC')
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
            return '<div class="px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-400 dark:text-slate-500 italic text-sm">Aucune modification enregistrée.</div>';
        }

        // Accent strip + header underline — full class strings so JIT can
        // compile them. Was a single hex shared across both visuals.
        $accentStripClass = match ($event) {
            'created' => 'bg-emerald-500 dark:bg-emerald-400',
            'updated' => 'bg-amber-500 dark:bg-amber-400',
            'deleted' => 'bg-red-500 dark:bg-red-400',
            default => 'bg-rose-500 dark:bg-rose-400',
        };
        $accentBorderClass = match ($event) {
            'created' => 'border-b-emerald-500 dark:border-b-emerald-400',
            'updated' => 'border-b-amber-500 dark:border-b-amber-400',
            'deleted' => 'border-b-red-500 dark:border-b-red-400',
            default => 'border-b-rose-500 dark:border-b-rose-400',
        };

        $rows = '';
        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            $isOnlyNew = is_null($oldVal) && !is_null($newVal);
            $isOnlyOld = !is_null($oldVal) && is_null($newVal);

            $oldDisplay = self::formatCellValue($oldVal);
            $newDisplay = self::formatCellValue($newVal);

            // Per-cell theming. The `null` case is a muted neutral; the
            // populated case is a red/green wash. Both halves include
            // dark-mode counterparts so the diff table stays legible.
            $oldCellClass = $isOnlyNew
                ? 'bg-slate-50 dark:bg-slate-900 text-slate-400 dark:text-slate-500'
                : 'bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300';
            $newCellClass = $isOnlyOld
                ? 'bg-slate-50 dark:bg-slate-900 text-slate-400 dark:text-slate-500'
                : 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300';

            $rows .= '<tr>'
                .'<td class="px-3.5 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 border-b border-slate-100 dark:border-slate-800 align-top whitespace-nowrap">'.e(self::humanizeFieldName($key)).'</td>'
                .'<td class="px-3.5 py-2 text-xs border-b border-slate-100 dark:border-slate-800 align-top break-all '.$oldCellClass.'">'.$oldDisplay.'</td>'
                .'<td class="px-1.5 py-2 text-sm text-slate-300 dark:text-slate-600 border-b border-slate-100 dark:border-slate-800 text-center align-top w-7">&#8594;</td>'
                .'<td class="px-3.5 py-2 text-xs border-b border-slate-100 dark:border-slate-800 align-top break-all '.$newCellClass.'">'.$newDisplay.'</td>'
                .'</tr>';
        }

        $countLabel = count($allKeys).' champ'.(count($allKeys) > 1 ? 's' : '').' modifié'.(count($allKeys) > 1 ? 's' : '');

        return '<div class="mt-1">'
            .'<div class="flex items-center gap-2.5 mb-2">'
            .'<div class="h-[3px] w-6 rounded-sm '.$accentStripClass.'"></div>'
            .'<span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Modifications détaillées</span>'
            .'<span class="text-[11px] text-slate-400 dark:text-slate-500">— '.$countLabel.'</span>'
            .'</div>'
            .'<div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">'
            .'<table class="w-full border-collapse table-fixed min-w-[380px]">'
            .'<colgroup><col class="w-[20%]"><col class="w-[36%]"><col class="w-[5%]"><col class="w-[39%]"></colgroup>'
            .'<thead><tr class="bg-slate-50 dark:bg-slate-900">'
            .'<th class="px-3.5 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-left border-b-2 '.$accentBorderClass.'">Champ</th>'
            .'<th class="px-3.5 py-2 text-[10px] font-bold uppercase tracking-wider text-red-700 dark:text-red-300 text-left border-b-2 '.$accentBorderClass.'">Avant</th>'
            .'<th class="px-1 py-2 border-b-2 '.$accentBorderClass.'"></th>'
            .'<th class="px-3.5 py-2 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300 text-left border-b-2 '.$accentBorderClass.'">Après</th>'
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
            return '<span class="italic text-slate-400 dark:text-slate-500">—</span>';
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
