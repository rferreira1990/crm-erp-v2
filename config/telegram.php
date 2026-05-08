<?php

$allowedUserIds = array_values(array_filter(array_map(
    static fn (string $value): int => (int) trim($value),
    explode(',', (string) env('TELEGRAM_ALLOWED_USER_IDS', ''))
), static fn (int $value): bool => $value > 0));

return [
    'enabled' => (bool) env('TELEGRAM_BOT_ENABLED', false),
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'allowed_user_ids' => $allowedUserIds,
    'email' => [
        'draft_ttl_minutes' => max(5, (int) env('TELEGRAM_EMAIL_DRAFT_TTL_MINUTES', 30)),
        'max_attachments' => max(1, (int) env('TELEGRAM_EMAIL_MAX_ATTACHMENTS', 5)),
        'max_file_bytes' => max(1_000_000, (int) env('TELEGRAM_EMAIL_MAX_FILE_BYTES', 10_485_760)),
    ],
];
