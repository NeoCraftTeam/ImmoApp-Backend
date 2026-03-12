<?php

declare(strict_types=1);

namespace App\Mail;

use App\Filament\Admin\Resources\AdReports\AdReportResource;
use App\Models\AdReport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewAdReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdReport $report,
        public User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'KeyHome - Nouveau signalement d\'annonce a traiter',
        );
    }

    public function content(): Content
    {
        $reviewUrl = rtrim((string) config('app.url'), '/')."/admin/ad-reports/{$this->report->id}/edit";

        try {
            $reviewUrl = AdReportResource::getUrl('edit', ['record' => $this->report->id], panel: 'admin');
        } catch (\Throwable) {
            // Keep fallback URL if Filament URL generation fails in non-panel contexts.
        }

        return new Content(
            view: 'emails.ad-report-notify-admin',
            with: [
                'report' => $this->report,
                'recipient' => $this->recipient,
                'reviewUrl' => $reviewUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
