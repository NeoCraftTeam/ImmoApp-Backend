<?php

declare(strict_types=1);

/**
 * PHPUnit runs this file before Laravel boots. CI copies `.env.example` (MAIL_MAILER=resend)
 * into `.env.testing`; without forcing the in-process transport here, tests hit the Resend API
 * and fail with an invalid key while assertions expect HTTP success.
 */
$_ENV['MAIL_MAILER'] = 'array';
$_SERVER['MAIL_MAILER'] = 'array';
putenv('MAIL_MAILER=array');

$_ENV['RESEND_KEY'] = '';
$_SERVER['RESEND_KEY'] = '';
putenv('RESEND_KEY=');

// ConversationController resolves MessageService → EncryptionService on every chat route.
// `.env.example` has no `CHAT_ENCRYPTION_KEY`; CI copies it to `.env.testing`.
//
// SECURITY: this key used to be a literal `0123…cdef` 32-byte hex string
// committed in source, which meant any test DB shipped to a fixture or
// CI artifact was decryptable by anyone with read access to the repo.
// Generate a fresh 32-byte key per test process — it has no business
// outliving the run.
$chatTestKey = bin2hex(random_bytes(32));
$_ENV['CHAT_ENCRYPTION_KEY'] = $chatTestKey;
$_SERVER['CHAT_ENCRYPTION_KEY'] = $chatTestKey;
putenv('CHAT_ENCRYPTION_KEY='.$chatTestKey);

require dirname(__DIR__).'/vendor/autoload.php';
