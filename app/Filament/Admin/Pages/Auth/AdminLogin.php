<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\Auth;

use Filament\Auth\Pages\Login;

/**
 * Custom admin login page with Passkey (WebAuthn) support.
 *
 * The passkey button is injected via the AUTH_LOGIN_FORM_AFTER render hook
 * registered in AdminPanelProvider.
 */
class AdminLogin extends Login {}
