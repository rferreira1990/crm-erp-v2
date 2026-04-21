<?php

namespace App\Support;

final class PurchasingComparisonRules
{
    /**
     * Regra futura:
     * "Mais barato total" só considera propostas completas.
     */
    public const TOTAL_REQUIRES_COMPLETE_PROPOSAL = true;

    /**
     * Regra futura:
     * Portes contam para o total global.
     */
    public const SHIPPING_COUNTS_IN_TOTAL = true;

    /**
     * Regra futura:
     * Comparação por item ignora linhas indisponíveis.
     */
    public const ITEM_COMPARISON_IGNORES_UNAVAILABLE = true;

    /**
     * Regra futura:
     * Alternativos não vencem automaticamente contra item exato.
     */
    public const ALTERNATIVE_DOES_NOT_SILENTLY_WIN_EXACT = true;

    /**
     * @return array<string, bool>
     */
    public static function asArray(): array
    {
        return [
            'total_requires_complete_proposal' => self::TOTAL_REQUIRES_COMPLETE_PROPOSAL,
            'shipping_counts_in_total' => self::SHIPPING_COUNTS_IN_TOTAL,
            'item_comparison_ignores_unavailable' => self::ITEM_COMPARISON_IGNORES_UNAVAILABLE,
            'alternative_does_not_silently_win_exact' => self::ALTERNATIVE_DOES_NOT_SILENTLY_WIN_EXACT,
        ];
    }
}

