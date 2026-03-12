<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdReports\Schemas;

use App\Enums\AdReportReason;
use App\Enums\AdReportStatus;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class AdReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Signalement')
                ->icon(Heroicon::OutlinedFlag)
                ->columns(2)
                ->schema([
                    Placeholder::make('ad_title')
                        ->label('Annonce')
                        ->content(fn ($record) => $record->ad->title ?? '—'),
                    Placeholder::make('ad_link')
                        ->label('Lien annonce')
                        ->content(fn ($record) => self::adLinks($record))
                        ->columnSpanFull(),
                    Placeholder::make('reporter_name')
                        ->label('Signale par')
                        ->content(fn ($record) => $record->reporter->fullname ?? '—'),
                    Placeholder::make('reporter_contact')
                        ->label('Email / Telephone reporter')
                        ->content(fn ($record) => self::userContactLine($record?->reporter?->email, $record?->reporter?->phone_number)),
                    Placeholder::make('owner_name')
                        ->label('Proprietaire annonce')
                        ->content(fn ($record) => $record->owner->fullname ?? $record->ad->user->fullname ?? '—'),
                    Placeholder::make('owner_contact')
                        ->label('Email / Telephone proprietaire')
                        ->content(fn ($record) => self::userContactLine(
                            $record->owner->email ?? $record->ad->user->email,
                            $record->owner->phone_number ?? $record->ad->user->phone_number,
                        )),
                    Placeholder::make('reason_label')
                        ->label('Motif')
                        ->content(fn ($record) => $record?->reason?->getLabel() ?? '—'),
                    Placeholder::make('scam_reason_label')
                        ->label('Detail arnaque')
                        ->content(fn ($record) => $record->reason === AdReportReason::SCAM
                            ? ($record->scam_reason?->getLabel() ?? 'Non precise')
                            : 'Non applicable'),
                    Placeholder::make('payment_methods')
                        ->label('Moyens de paiement cites')
                        ->content(fn ($record) => self::paymentMethodsLabel($record?->payment_methods)),
                    Placeholder::make('description')
                        ->label('Description utilisateur')
                        ->content(fn ($record) => $record?->description ?: 'Aucune description fournie.'),
                    Placeholder::make('submitted_at')
                        ->label('Date de soumission')
                        ->content(fn ($record) => $record->created_at?->format('d/m/Y H:i:s') ?? '—'),
                    Placeholder::make('report_id')
                        ->label('Reference')
                        ->content(fn ($record) => $record->id ?? '—'),
                    Placeholder::make('ip_address')
                        ->label('Adresse IP')
                        ->content(fn ($record) => $record?->ip_address ?: '—'),
                    Placeholder::make('user_agent')
                        ->label('User agent')
                        ->content(fn ($record) => $record?->user_agent ?: '—')
                        ->columnSpanFull(),
                ]),

            Section::make('Traitement admin')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label('Statut')
                        ->options(AdReportStatus::class)
                        ->required()
                        ->native(false)
                        ->columnSpanFull(),
                    Textarea::make('admin_notes')
                        ->label('Notes internes')
                        ->rows(5)
                        ->maxLength(2000)
                        ->placeholder('Analyse interne, actions prises, conclusion...')
                        ->columnSpanFull(),
                    Placeholder::make('resolved_at')
                        ->label('Cloture le')
                        ->content(fn ($record) => $record?->resolved_at?->format('d/m/Y H:i:s') ?? '—'),
                    Placeholder::make('resolved_by')
                        ->label('Cloture par')
                        ->content(fn ($record) => $record->resolver->fullname ?? '—'),
                ]),
        ]);
    }

    private static function userContactLine(?string $email, ?string $phone): string
    {
        $safeEmail = filled($email) ? $email : '—';
        $safePhone = filled($phone) ? $phone : '—';

        return "{$safeEmail} | {$safePhone}";
    }

    private static function paymentMethodsLabel(?array $paymentMethods): string
    {
        if (empty($paymentMethods)) {
            return 'Non applicable';
        }

        return implode(', ', $paymentMethods);
    }

    private static function adLinks(mixed $record): HtmlString|string
    {
        $ad = $record->ad;

        if (!$ad) {
            return '—';
        }

        if (!filled($ad->id) || !filled($ad->slug)) {
            return '—';
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $publicUrl = "{$frontendUrl}/ads/{$ad->id}/{$ad->slug}";

        return new HtmlString('<a href="'.$publicUrl.'" target="_blank" rel="noopener noreferrer" style="color:#0f766e;font-weight:600;text-decoration:underline;">Voir sur le site client</a>');
    }
}
