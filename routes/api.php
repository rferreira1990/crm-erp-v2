<?php

use App\Http\Controllers\Telegram\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook/{secret}', TelegramWebhookController::class)
    ->middleware('throttle:telegram-webhook')
    ->name('telegram.webhook');
