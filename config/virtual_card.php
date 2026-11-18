<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Virtual Card Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Flutterwave signs virtual-card webhook calls with the hash configured
    | here. Read through config() so it survives `config:cache` in
    | production, where env() returns null outside config files.
    |
    */

    'webhook_secret' => env('SECRET_HASH', ''),

];
