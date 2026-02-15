<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Widgets;

use App\Models\Ad;
use App\Models\AdInteraction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TopAdsTable extends BaseWidget
{
    protected static ?string $heading = 'Top Annonces';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $since = now()->subDays(30);

        return $table
            ->query(
                Ad::query()
                    ->where('user_id', $user->id)
                    ->withCount([
                        'interactions as views_count' => fn (Builder $q) => $q
                            ->where('type', AdInteraction::TYPE_VIEW)
                            ->where('created_at', '>=', $since),
                        'interactions as favorites_count' => fn (Builder $q) => $q
                            ->where('type', AdInteraction::TYPE_FAVORITE)
                            ->where('created_at', '>=', $since),
                        'interactions as contacts_count' => fn (Builder $q) => $q
                            ->whereIn('type', [AdInteraction::TYPE_CONTACT_CLICK, AdInteraction::TYPE_PHONE_CLICK])
                            ->where('created_at', '>=', $since),
                    ])
                    ->orderByDesc('views_count')
            )
            ->columns([
                TextColumn::make('title')
                    ->label('Annonce')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (mixed $state): string => match ($state->value ?? $state) {
                        'available' => 'success',
                        'pending' => 'warning',
                        'unavailable' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('views_count')
                    ->label('👁 Vues')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('favorites_count')
                    ->label('❤️ Favoris')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('contacts_count')
                    ->label('📞 Contacts')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('views_count', 'desc')
            ->paginated([5]);
    }
}
