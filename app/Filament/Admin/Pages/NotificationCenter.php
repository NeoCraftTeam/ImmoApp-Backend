<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AdminBroadcastNotification;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Notifications\DatabaseNotification;
use UnitEnum;

/**
 * Admin notification center for sending targeted notifications to users.
 *
 * @property-read Schema $form
 */
class NotificationCenter extends Page
{
    protected static string|null|UnitEnum $navigationGroup = 'Communication';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::BellAlert;

    protected static ?string $navigationLabel = 'Centre de notifications';

    protected static ?string $title = 'Centre de notifications';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.admin.pages.notification-center';

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Envoyer une notification')
                    ->description('Envoyez une notification à un groupe d\'utilisateurs')
                    ->icon(Heroicon::PaperAirplane)
                    ->schema([
                        TextInput::make('title')
                            ->label('Titre')
                            ->required()
                            ->maxLength(255),
                        RichEditor::make('body')
                            ->label('Message')
                            ->required()
                            ->maxLength(2000),
                        Select::make('target')
                            ->label('Destinataires')
                            ->options([
                                'all' => 'Tous les utilisateurs',
                                'admins' => 'Administrateurs uniquement',
                                'agents' => 'Agents uniquement',
                                'customers' => 'Clients uniquement',
                            ])
                            ->required()
                            ->default('all'),
                    ])
                    ->footerActions([
                        Action::make('send')
                            ->label('Envoyer')
                            ->icon(Heroicon::PaperAirplane)
                            ->requiresConfirmation()
                            ->action(function (): void {
                                $this->sendNotification();
                            }),
                    ]),
                Section::make('Statistiques')
                    ->description('Aperçu des notifications')
                    ->icon(Heroicon::ChartBar)
                    ->schema([])
                    ->extraAttributes(['class' => 'grid grid-cols-3 gap-4']),
            ]);
    }

    public function sendNotification(): void
    {
        $data = $this->data;

        $query = User::query()->where('is_active', true);

        $target = $data['target'] ?? 'all';
        if ($target === 'admins') {
            $query->where('role', UserRole::ADMIN);
        } elseif ($target === 'agents') {
            $query->where('role', UserRole::AGENT);
        } elseif ($target === 'customers') {
            $query->where('role', UserRole::CUSTOMER);
        }

        $count = 0;
        $query->chunk(100, function ($users) use ($data, &$count): void {
            foreach ($users as $user) {
                $user->notify(
                    new AdminBroadcastNotification(
                        strip_tags($data['title'] ?? ''),
                        strip_tags($data['body'] ?? ''),
                    )
                );
                $count++;
            }
        });

        $this->data = [];

        Notification::make()
            ->title('Notifications envoyées')
            ->body("{$count} utilisateur(s) notifié(s).")
            ->success()
            ->send();
    }

    /**
     * @return array{total: int, unread: int, today: int}
     */
    public function getStats(): array
    {
        return [
            'total' => DatabaseNotification::query()->count(),
            'unread' => DatabaseNotification::query()->whereNull('read_at')->count(),
            'today' => DatabaseNotification::query()->whereDate('created_at', today())->count(),
        ];
    }
}
