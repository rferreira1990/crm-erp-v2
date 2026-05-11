<?php

return [
    'enabled' => (bool) env('AI_ASSISTANT_ENABLED', false),

    'model' => env('AI_MODEL', 'gpt-5.4-nano'),

    'timeout' => (int) env('AI_TIMEOUT', 20),

    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 1200),

    'pricing' => [
        'input_eur_per_1m' => (float) env('AI_PRICING_INPUT_EUR_PER_1M', 0),
        'output_eur_per_1m' => (float) env('AI_PRICING_OUTPUT_EUR_PER_1M', 0),
    ],

    'monthly_budget_eur' => is_numeric(env('AI_MONTHLY_BUDGET_EUR'))
        ? (float) env('AI_MONTHLY_BUDGET_EUR')
        : null,

    'budget_warning_percent' => (int) env('AI_BUDGET_WARNING_PERCENT', 80),

    'budget_hard_stop_enabled' => (bool) env('AI_BUDGET_HARD_STOP_ENABLED', true),
];
