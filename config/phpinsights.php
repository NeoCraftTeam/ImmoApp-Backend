<?php

declare(strict_types=1);

use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenFinalClasses;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenNormalClasses;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenSecurityIssues;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenTraits;
use NunoMaduro\PhpInsights\Domain\Metrics\Architecture\Classes;
use PHP_CodeSniffer\Standards\Generic\Sniffs\Formatting\SpaceAfterNotSniff;

return [
    'preset' => 'laravel',
    'ide' => 'vscode',
    'exclude' => [
        //  'path/to/directory' or 'path/to/file.php'
    ],
    'add' => [
        Classes::class => [
            ForbiddenFinalClasses::class,
        ],
    ],
    'remove' => [
        SpaceAfterNotSniff::class,
        ForbiddenNormalClasses::class,
        ForbiddenTraits::class,
        // Transitive deps (e.g. league/commonmark, phpseclib) — track via `composer audit` / Dependabot
        ForbiddenSecurityIssues::class,
    ],
    'config' => [
        //  ...
    ],
    'requirements' => [
        /*
         * Thresholds track the current codebase snapshot from `phpinsights analyse`.
         * When large refactors land, re-run insights and tune if the gate becomes
         * noisy versus real regressions.
         */
        'min-quality' => 67,
        'min-complexity' => 81,
        'min-architecture' => 60,
        'min-style' => 80,
        'disable-security-check' => true,
    ],
];
