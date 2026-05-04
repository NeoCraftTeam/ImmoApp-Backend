<?php

declare(strict_types=1);

namespace App\Filament\Exports\Concerns;

use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Facades\CauserResolver;

/**
 * Adds a French completion notification body and writes an audit-log entry
 * (`log_name=admin`, event=`exported`) every time an Export finishes.
 *
 * Concrete exporters MUST override `humanModelLabel()` (e.g. "utilisateurs",
 * "annonces", "paiements") so the notification + audit message read naturally.
 */
trait LogsExportActivity
{
    /**
     * Human-readable French label for the exported model in plural form.
     * E.g. "utilisateurs", "annonces", "paiements".
     */
    abstract protected static function humanModelLabel(): string;

    public static function getCompletedNotificationBody(Export $export): string
    {
        $modelLabel = static::humanModelLabel();
        $successful = number_format((int) $export->successful_rows, thousands_separator: ' ');

        $body = "L'export des {$modelLabel} est terminé : {$successful} ligne(s) exportée(s).";

        $failed = $export->getFailedRowsCount();
        if ($failed > 0) {
            $failedFmt = number_format($failed, thousands_separator: ' ');
            $body .= " {$failedFmt} ligne(s) ont échoué.";
        }

        // Best-effort audit log entry. Failures must never break the notification.
        try {
            self::writeAuditLog($export, $modelLabel, (int) $export->successful_rows, (int) $failed);
        } catch (\Throwable $e) {
            Log::warning('Export activity log failed', [
                'export_id' => $export->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $body;
    }

    private static function writeAuditLog(Export $export, string $modelLabel, int $successful, int $failed): void
    {
        $causer = $export->user_id ? User::query()->find($export->user_id) : CauserResolver::resolve();
        if ($causer === null) {
            return;
        }

        activity('admin')
            ->causedBy($causer)
            ->withProperties([
                'export_id' => $export->id,
                'file_name' => $export->file_name,
                'file_disk' => $export->file_disk,
                'format' => self::exportFormatForAudit($export),
                'successful_rows' => $successful,
                'failed_rows' => $failed,
                'exporter' => static::class,
                'model_label' => $modelLabel,
            ])
            ->event('exported')
            ->log("Export des {$modelLabel} terminé ({$successful} ligne(s))");
    }

    private static function exportFormatForAudit(Export $export): ?string
    {
        if (!array_key_exists('format', $export->getAttributes())) {
            return null;
        }

        $format = $export->getAttribute('format');
        if ($format instanceof \BackedEnum) {
            return (string) $format->value;
        }

        if (is_scalar($format)) {
            return (string) $format;
        }

        return null;
    }
}
