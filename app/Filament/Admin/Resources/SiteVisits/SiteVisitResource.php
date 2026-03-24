<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SiteVisits;

use App\Filament\Admin\Resources\SiteVisits\Pages\ManageSiteVisits;
use App\Models\SiteVisit;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SiteVisitResource extends Resource
{
    protected static ?string $model = SiteVisit::class;

    protected static ?string $slug = 'site-visits';

    protected static string|\UnitEnum|null $navigationGroup = 'Analytique';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Visites (UTM)';

    protected static ?string $modelLabel = 'Visite';

    protected static ?string $pluralModelLabel = 'Visites du site';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'session_id';

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user']);
    }

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('visited_at', 'desc')
            ->columns([
                TextColumn::make('visited_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Canal')
                    ->badge()
                    ->sortable(),
                TextColumn::make('session_id')
                    ->label('Session')
                    ->limit(12)
                    ->tooltip(fn (SiteVisit $record): string => $record->session_id)
                    ->copyable(),
                TextColumn::make('utm_source')
                    ->label('utm_source')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('utm_medium')
                    ->label('utm_medium')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('utm_campaign')
                    ->label('utm_campaign')
                    ->limit(24)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('utm_content')
                    ->label('utm_content')
                    ->limit(20)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('utm_term')
                    ->label('utm_term')
                    ->limit(20)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('referrer_domain')
                    ->label('Référent')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('device_type')
                    ->label('Appareil')
                    ->badge()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Utilisateur lié')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Canal')
                    ->options([
                        'direct' => 'Direct',
                        'organic' => 'Organique',
                        'social' => 'Social',
                        'referral' => 'Référence',
                        'paid' => 'Payant',
                        'email' => 'Email',
                    ]),
                Filter::make('visited_at')
                    ->label('Période')
                    ->form([
                        DatePicker::make('from')->label('Du')->native(false),
                        DatePicker::make('until')->label('Au')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('visited_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('visited_at', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Depuis le '.Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Jusqu\'au '.Carbon::parse($data['until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
            ]);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageSiteVisits::route('/'),
        ];
    }

    #[\Override]
    public static function canCreate(): bool
    {
        return false;
    }

    #[\Override]
    public static function canEdit($record): bool
    {
        return false;
    }

    #[\Override]
    public static function canDelete($record): bool
    {
        return false;
    }
}
