<?php

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

return [

    /*
    |--------------------------------------------------------------------------
    | API Rate Limits
    |--------------------------------------------------------------------------
    */

    'api' => [
        'download' => Limit::perMinute(10)->by(fn () => auth()->id() ?? request()->header('X-Guest-Token', 'anonymous')),
        'batch' => Limit::perHour(5)->by(fn () => auth()->id() ?? request()->header('X-Guest-Token', 'anonymous')),
        'extension' => Limit::perMinute(30)->by(fn () => request()->header('X-API-Token', 'anonymous')),
    ],

];
