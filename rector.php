<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/bootstrap/cache',
    ])
    // active la modernisation vers PHP 8.4
    ->withPhpSets(php84: true)
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(10)
    ->withCodeQualityLevel(10)
    ->withSetProviders(LaravelSetProvider::class)
    ->withComposerBased(laravel: true);
