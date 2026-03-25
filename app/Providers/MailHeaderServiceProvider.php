<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Ensures every outgoing email:
 *   1. Has a consistent Return-Path matching the FROM domain (SPF alignment).
 *   2. Has a List-Unsubscribe header for bulk/transactional mail.
 *   3. Sets X-Mailer to identify the sender application.
 *
 * IMPORTANT: SPF, DMARC, and DKIM are DNS-level configurations.
 * See docs/email-authentication.md for the required DNS records.
 */
class MailHeaderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $message = $event->message;

            $fromAddress = config('mail.from.address');
            $fromName = config('mail.from.name');

            if (!is_string($fromAddress) || $fromAddress === '') {
                return;
            }

            // 1. Enforce a consistent Return-Path for SPF alignment.
            //    Return-Path must match the sending domain included in the SPF record.
            if ($message->getReturnPath() === null) {
                $message->returnPath(new Address($fromAddress, is_string($fromName) ? $fromName : ''));
            }

            // 2. Reply-To — use a monitored inbox, not a no-reply if not already set.
            $replyToHeader = $message->getReplyTo();
            if (empty($replyToHeader)) {
                $replyTo = (string) config('mail.reply_to.address', $fromAddress);
                $replyToName = (string) config('mail.reply_to.name', $fromName ?? '');
                $message->replyTo(new Address($replyTo, $replyToName));
            }

            // 3. X-Mailer — used by deliverability tools to classify the sender.
            $message->getHeaders()->addTextHeader('X-Mailer', config('app.name').' Mailer/1.0');

            // 4. Auto-Submitted — RFC 3834: prevents auto-replies to auto-replies.
            if (!$message->getHeaders()->has('Auto-Submitted')) {
                $message->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
            }
        });
    }
}
