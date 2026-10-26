<?php

use Illuminate\Support\Facades\Route;
use KareliaLabs\PaymenterSso\Http\Controllers\SignInController;

// /extensions/paymenter-sso/... in the panel's web group (session, cookies, CSRF protection for POST). Public on
// purpose: these are the steps of signing in. Starting and finishing a sign-in share the panel's own sign-in rate
// limit (10 per minute per IP address, together with password and two-factor attempts).
Route::get('/state', [SignInController::class, 'state'])->middleware('throttle:60,1');
Route::post('/start', [SignInController::class, 'start'])->middleware('throttle:authentication');
Route::get('/callback', [SignInController::class, 'callback'])->middleware('throttle:authentication');
