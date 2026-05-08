<?php

namespace App\DTO\Telegram;

final readonly class TelegramAiIntentData
{
    public const INTENT_STOCK_LOOKUP = 'stock_lookup';
    public const INTENT_PENDING_QUOTES_LOOKUP = 'pending_quotes_lookup';
    public const INTENT_QUOTE_INFO_LOOKUP = 'quote_info_lookup';
    public const INTENT_CUSTOMER_BALANCE_LOOKUP = 'customer_balance_lookup';
    public const INTENT_SUPPLIER_BALANCE_LOOKUP = 'supplier_balance_lookup';
    public const INTENT_CREATE_CONSTRUCTION_SITE_DAILY_LOG = 'create_construction_site_daily_log';
    public const INTENT_SEND_EMAIL_START = 'send_email_start';
    public const INTENT_UNKNOWN = 'unknown';

    public function __construct(
        public string $intent,
        public ?string $term,
        public ?float $confidence,
        public ?string $siteTerm = null,
        public ?string $description = null,
    ) {
    }

    public static function unknown(): self
    {
        return new self(
            intent: self::INTENT_UNKNOWN,
            term: null,
            confidence: null
        );
    }
}
