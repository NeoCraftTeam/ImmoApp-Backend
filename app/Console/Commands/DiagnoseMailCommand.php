<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Command\Command as CommandAlias;

class DiagnoseMailCommand extends Command
{
    protected $signature = 'mail:diagnose {--send-to= : Email address to send a test OTP to}';

    protected $description = 'Diagnose mail and queue configuration for email verification';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan>======= KeyHome Mail Diagnostics =======</>');
        $this->newLine();

        // 1. Mail configuration
        $mailer = config('mail.default');
        $from = config('mail.from.address');

        // Resend-specific
        $resendKey = config('services.resend.key', '');
        $resendKeyMasked = $resendKey
            ? substr((string) $resendKey, 0, 6).'****'.substr((string) $resendKey, -4)
            : '(not set)';

        // Named senders
        $senderNoreply = config('mail.senders.noreply.address', '(not set)');
        $senderSupport = config('mail.senders.support.address', '(not set)');
        $senderMarketing = config('mail.senders.marketing.address', '(not set)');

        $this->line('<fg=yellow>1. Mail Configuration</>');
        $this->table(['Key', 'Value'], [
            ['MAIL_MAILER',             $mailer],
            ['MAIL_FROM_ADDRESS',       $from ?? '(not set)'],
            ['RESEND_KEY',              $resendKeyMasked],
            ['sender: noreply',         $senderNoreply],
            ['sender: support',         $senderSupport],
            ['sender: marketing',       $senderMarketing],
        ]);

        if ($mailer === 'log') {
            $this->error('  ⚠  MAIL_MAILER=log — emails are written to the log file, NOT sent!');
            $this->line('     Fix: set MAIL_MAILER=resend + RESEND_KEY=re_xxx in your .env');
        } elseif ($mailer === 'array') {
            $this->error('  ⚠  MAIL_MAILER=array — emails are discarded in memory, NOT sent!');
        } elseif ($mailer === 'resend' && empty($resendKey)) {
            $this->error('  ⚠  MAIL_MAILER=resend but RESEND_KEY is not set!');
            $this->line('     Fix: add RESEND_KEY=re_xxxx to your .env (get it at resend.com)');
        } elseif ($mailer === 'resend') {
            $this->info('  ✓  Resend mailer configured.');
            // SMTP fallback still shown for info
            $smtpHost = config('mail.mailers.smtp.host');
            if ($smtpHost) {
                $this->line("     SMTP fallback available: {$smtpHost}");
            }
        } else {
            $this->info("  ✓  Mailer driver '{$mailer}' looks correct.");
        }
        $this->newLine();

        // 2. Queue configuration
        $queue = config('queue.default');
        $this->line('<fg=yellow>2. Queue Configuration</>');
        $this->table(['Key', 'Value'], [
            ['QUEUE_CONNECTION', $queue],
        ]);

        if ($queue !== 'sync') {
            $this->warn("  ⚠  QUEUE_CONNECTION={$queue} — mail is dispatched to a queue.");
            $this->line('     A queue worker must be running: php artisan queue:work');
            $this->line('     Or set QUEUE_CONNECTION=sync to send emails inline.');
            // Validate queue health for non-sync drivers
            try {
                $failedCount = (int) DB::table('failed_jobs')->count();
                if ($failedCount > 0) {
                    $this->warn("     ⚠  {$failedCount} job(s) currently in failed_jobs — run: php artisan queue:failed");
                } else {
                    $this->info('     ✓  No failed jobs.');
                }
            } catch (\Throwable $e) {
                $this->line('     (could not check failed_jobs: '.$e->getMessage().')');
            }
            if ($mailer === 'resend' && empty($resendKey)) {
                $this->error('     ✗  Resend selected but RESEND_KEY empty — queued jobs will fail.');
            }
        } else {
            $this->info('  ✓  QUEUE_CONNECTION=sync — emails are sent inline (no worker needed).');
        }
        $this->newLine();

        // 3. Cache configuration
        $cacheDriver = config('cache.default');
        $this->line('<fg=yellow>3. Cache Configuration (OTP storage)</>');
        $this->table(['Key', 'Value'], [
            ['CACHE_STORE', $cacheDriver],
        ]);

        if ($cacheDriver === 'array') {
            $this->error('  ⚠  CACHE_STORE=array — OTP codes are lost between requests!');
            $this->line('     Fix: set CACHE_STORE=redis or CACHE_STORE=database in your .env');
        } else {
            $this->info("  ✓  Cache driver '{$cacheDriver}' persists OTPs across requests.");
        }
        $this->newLine();

        // 4. App URL / callback URLs
        $this->line('<fg=yellow>4. Callback URLs</>');
        $this->table(['Key', 'Value'], [
            ['APP_URL', config('app.url')],
            ['EMAIL_VERIFY_CALLBACK', config('app.email_verify_callback', '(not set)')],
        ]);
        $this->newLine();

        // 5. Optional live send test
        $sendTo = $this->option('send-to');
        if ($sendTo) {
            $this->line("<fg=yellow>5. Live Send Test → {$sendTo}</>");

            $user = User::where('email', $sendTo)->first();
            if (!$user) {
                $this->warn("  No user found with email {$sendTo}. Creating a mock send using Mail::raw().");
                try {
                    Mail::raw('Test KeyHome mail — configuration is working.', function ($m) use ($sendTo): void {
                        $m->to($sendTo)->subject('[KeyHome] Test mail');
                    });
                    $this->info('  ✓  Test email dispatched successfully.');
                } catch (\Throwable $e) {
                    $this->error('  ✗  Send failed: '.$e->getMessage());
                }
            } else {
                $this->line("  Found user #{$user->id}. Triggering sendEmailVerificationNotification()...");
                // Force resend by clearing cooldown keys
                Cache::forget('email_otp_'.$user->id);
                Cache::forget('email_otp_sent_'.$user->id);
                try {
                    $user->sendEmailVerificationNotification();
                    $otp = Cache::get('email_otp_'.$user->id);
                    if ($otp) {
                        $this->info("  ✓  OTP generated and stored in cache: {$otp}");
                        $this->line('     Check your inbox (or log file if MAIL_MAILER=log).');
                    } else {
                        $this->error('  ✗  OTP was not stored in cache — check CACHE_STORE setting.');
                    }
                } catch (\Throwable $e) {
                    $this->error('  ✗  Notification failed: '.$e->getMessage());
                }
            }
            $this->newLine();
        }

        $this->line('<fg=cyan>========================================</>');
        $this->newLine();

        return CommandAlias::SUCCESS;
    }
}
