<?php

namespace App\DTO\Telegram;

final readonly class TelegramAiIntentData
{
    public const INTENT_STOCK_LOOKUP = 'stock_lookup';
    public const INTENT_PENDING_QUOTES_LOOKUP = 'pending_quotes_lookup';
    public const INTENT_QUOTE_INFO_LOOKUP = 'quote_info_lookup';
    public const INTENT_CUSTOMER_QUOTES_LOOKUP = 'customer_quotes_lookup';
    public const INTENT_CUSTOMER_BALANCE_LOOKUP = 'customer_balance_lookup';
    public const INTENT_SUPPLIER_BALANCE_LOOKUP = 'supplier_balance_lookup';
    public const INTENT_KPI_LOOKUP = 'kpi_lookup';
    public const INTENT_OVERDUE_CUSTOMERS_LOOKUP = 'overdue_customers_lookup';
    public const INTENT_OVERDUE_SUPPLIERS_LOOKUP = 'overdue_suppliers_lookup';
    public const INTENT_QUOTES_FOLLOWUP_LOOKUP = 'quotes_followup_lookup';
    public const INTENT_CREATE_CONSTRUCTION_SITE_DAILY_LOG = 'create_construction_site_daily_log';
    public const INTENT_CALENDAR_EVENT_CREATE = 'calendar_event_create';
    public const INTENT_SEND_EMAIL_START = 'send_email_start';
    public const INTENT_UNKNOWN = 'unknown';

    /**
     * @param array{
     *   title?:string|null,
     *   description?:string|null,
     *   type?:string|null,
     *   starts_at_text?:string|null,
     *   ends_at_text?:string|null,
     *   date_text?:string|null,
     *   time_text?:string|null,
     *   customer_term?:string|null,
     *   supplier_term?:string|null,
     *   construction_site_term?:string|null,
     *   assigned_user_term?:string|null,
     *   priority?:string|null,
     *   all_day?:bool|null
     * }|null $data
     */
    public function __construct(
        public string $intent,
        public ?string $term,
        public ?float $confidence,
        public ?string $siteTerm = null,
        public ?string $description = null,
        public ?array $data = null,
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
