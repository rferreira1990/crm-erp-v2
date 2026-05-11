<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramWebhookService $webhookService
    ) {
    }

    public function __invoke(Request $request, string $secret): JsonResponse
    {
        if (! config('telegram.enabled')) {
            abort(404);
        }

        $configuredSecret = (string) config('telegram.webhook_secret', '');
        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $secret)) {
            abort(404);
        }

        $this->webhookService->handle($request->all());

        return response()->json([
            'ok' => true,
        ]);
    }
}
