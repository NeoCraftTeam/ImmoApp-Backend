<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AdReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdReportReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdReport $report,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'KeyHome - Nous avons bien recu votre signalement',
        );
    }

    public function content(): Content
    {
        $reportReference = 'RPT-'.strtoupper(substr(str_replace('-', '', $this->report->id), 0, 10));

        return new Content(
            view: 'emails.ad-report-received',
            with: [
                'report' => $this->report,
                'reportReference' => $reportReference,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
