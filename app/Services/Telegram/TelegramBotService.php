<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramBotService
{
    public function sendMessage(int|string $chatId, string $text): void
    {
        $token = trim((string) config('telegram.bot_token', ''));
        if ($token === '') {
            Log::warning('Telegram message skipped because bot token is missing.');

            return;
        }

        $endpoint = sprintf('https://api.telegram.org/bot%s/sendMessage', $token);

        try {
            $response = Http::timeout(10)->post($endpoint, [
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            if (! $response->successful()) {
                Log::warning('Telegram API sendMessage failed.', [
                    'status' => $response->status(),
                    'chat_id' => $chatId,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Telegram API sendMessage exception.', [
                'chat_id' => $chatId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function sendDocument(int|string $chatId, string $filePath, ?string $caption = null): void
    {
        $token = trim((string) config('telegram.bot_token', ''));
        if ($token === '') {
            Log::warning('Telegram document skipped because bot token is missing.');

            return;
        }

        $normalizedPath = trim($filePath);
        if ($normalizedPath === '' || ! is_file($normalizedPath)) {
            Log::warning('Telegram document skipped because file does not exist.', [
                'chat_id' => $chatId,
            ]);

            return;
        }

        $endpoint = sprintf('https://api.telegram.org/bot%s/sendDocument', $token);

        $stream = null;

        try {
            $stream = fopen($normalizedPath, 'rb');
            if ($stream === false) {
                Log::warning('Telegram document skipped because file could not be opened.', [
                    'chat_id' => $chatId,
                ]);

                return;
            }

            $payload = [
                'chat_id' => $chatId,
            ];

            if ($caption !== null && trim($caption) !== '') {
                $payload['caption'] = trim($caption);
            }

            $response = Http::timeout(30)
                ->attach('document', $stream, basename($normalizedPath))
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::warning('Telegram API sendDocument failed.', [
                    'status' => $response->status(),
                    'chat_id' => $chatId,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Telegram API sendDocument exception.', [
                'chat_id' => $chatId,
                'error' => $exception->getMessage(),
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function getFilePath(string $telegramFileId): ?string
    {
        $token = trim((string) config('telegram.bot_token', ''));
        if ($token === '' || trim($telegramFileId) === '') {
            return null;
        }

        $endpoint = sprintf('https://api.telegram.org/bot%s/getFile', $token);

        try {
            $response = Http::timeout(10)->post($endpoint, [
                'file_id' => $telegramFileId,
            ]);

            if (! $response->successful()) {
                Log::warning('Telegram API getFile failed.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            $ok = (bool) ($data['ok'] ?? false);
            $path = is_array($data['result'] ?? null)
                ? (string) ($data['result']['file_path'] ?? '')
                : '';

            if (! $ok || trim($path) === '') {
                return null;
            }

            return trim($path);
        } catch (Throwable $exception) {
            Log::warning('Telegram API getFile exception.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function getFile(string $telegramFileId): ?string
    {
        return $this->getFilePath($telegramFileId);
    }

    public function downloadFileContents(string $telegramFilePath): ?string
    {
        $token = trim((string) config('telegram.bot_token', ''));
        $path = trim($telegramFilePath);
        if ($token === '' || $path === '') {
            return null;
        }

        $endpoint = sprintf('https://api.telegram.org/file/bot%s/%s', $token, ltrim($path, '/'));

        try {
            $response = Http::timeout(20)->get($endpoint);
            if (! $response->successful()) {
                Log::warning('Telegram file download failed.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->body();
        } catch (Throwable $exception) {
            Log::warning('Telegram file download exception.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function downloadFile(string $telegramFilePath): ?string
    {
        return $this->downloadFileContents($telegramFilePath);
    }
}
