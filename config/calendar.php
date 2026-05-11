<?php

return [
    'caldav' => [
        // Minimum seconds between automatic sync attempts for each company.
        'auto_sync_interval_seconds' => (int) env('CALDAV_AUTO_SYNC_INTERVAL_SECONDS', 300),

        // Lock duration while one sync is running for a company.
        'auto_sync_lock_seconds' => (int) env('CALDAV_AUTO_SYNC_LOCK_SECONDS', 90),

        // Event window considered for periodic auto sync.
        'auto_sync_past_days' => (int) env('CALDAV_AUTO_SYNC_PAST_DAYS', 45),
        'auto_sync_future_days' => (int) env('CALDAV_AUTO_SYNC_FUTURE_DAYS', 365),
        'auto_sync_limit' => (int) env('CALDAV_AUTO_SYNC_LIMIT', 300),
    ],
];

