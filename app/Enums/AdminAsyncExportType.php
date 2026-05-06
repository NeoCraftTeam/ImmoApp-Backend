<?php

declare(strict_types=1);

namespace App\Enums;

enum AdminAsyncExportType: string
{
    case MetricsCsv = 'metrics_csv';
    case MetricsPdf = 'metrics_pdf';
    case UsersCsv = 'users_csv';
    case AdsCsv = 'ads_csv';
    case PaymentsCsv = 'payments_csv';
    case SurveyResponsesCsv = 'survey_responses_csv';
}
