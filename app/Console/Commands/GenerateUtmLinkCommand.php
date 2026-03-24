<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Builds a marketing URL with UTM query parameters for QA and campaign links.
 */
class GenerateUtmLinkCommand extends Command
{
    protected $signature = 'utm:generate
        {--base-url= : Absolute landing URL (default: config app.frontend_url or https://keyhome.app) }
        {--source= : utm_source (required) }
        {--medium= : utm_medium (required) }
        {--campaign= : utm_campaign }
        {--content= : utm_content }
        {--term= : utm_term }';

    protected $description = 'Print a URL with UTM parameters for tracking acquisition in KeyHome';

    public function handle(): int
    {
        $base = (string) ($this->option('base-url') ?: config('app.frontend_url') ?: 'https://keyhome.app');
        $source = $this->option('source');
        $medium = $this->option('medium');

        if (!is_string($source) || trim($source) === '') {
            $this->error('The --source option is required (e.g. --source=tiktok).');

            return self::FAILURE;
        }

        if (!is_string($medium) || trim($medium) === '') {
            $this->error('The --medium option is required (e.g. --medium=cpc).');

            return self::FAILURE;
        }

        $params = array_filter([
            'utm_source' => trim($source),
            'utm_medium' => trim($medium),
            'utm_campaign' => is_string($this->option('campaign')) ? trim($this->option('campaign')) : '',
            'utm_content' => is_string($this->option('content')) ? trim($this->option('content')) : '',
            'utm_term' => is_string($this->option('term')) ? trim($this->option('term')) : '',
        ], fn (string $v): bool => $v !== '');

        $url = rtrim($base, '?&');
        $sep = str_contains($url, '?') ? '&' : '?';
        $this->line($url.$sep.http_build_query($params));

        $this->newLine();
        $this->comment('Visitors should load this URL in the browser so the Next.js app can capture UTMs and call POST /api/v1/track/visit.');

        return self::SUCCESS;
    }
}
