<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MassiveAdSeeder Fast Mode
    |--------------------------------------------------------------------------
    |
    | When true: 200 ads, 2-3 images each (~10x faster for dev).
    | When false: 2000 ads, 5-7 images each (full production-like seed).
    |
    */
    'massive_ad_fast_mode' => env('SEED_FAST_MODE', true),

];
