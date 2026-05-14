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

require dirname(__DIR__).'/vendor/autoload.php';
