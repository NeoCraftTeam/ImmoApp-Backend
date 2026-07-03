<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // Generic registration message (OWASP Authentication Cheat Sheet —
    // "Account creation"): never confirms an account already exists nor
    // reveals its sign-in method, to prevent user enumeration and provider
    // disclosure.
    'registration_generic_conflict' => 'We could not complete registration with these details. If you already have an account, sign in or use "Forgot password".',

];
