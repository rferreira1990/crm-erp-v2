<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\AiBudgetExceededException;
use App\Models\AiUsageLog;
use App\Models\Company;

class AiUsageBudgetService
{
    /**
     * @return array{
     *   used_eur:float,
     *   budget_eur:float|null,
     *   remaining_eur:float|null,
     *   warning_percent:int,
     *   usage_percent:float|null,
     *   is_warning:bool,
     *   is_exceeded:bool,
     *   hard_stop_enabled:bool
     * }
     */
    public function getMonthlyUsage(int $companyId): array
    {
        $company = Company::query()->whereKey($companyId)->firstOrFail();
        $budget = $this->resolveBudget($company);
        $warningPercent = $this->resolveWarningPercent($company);
        $hardStopEnabled = $this->resolveHardStopEnabled($company);

        $range = $this->resolveCurrentMonthRange();

        $used = (float) AiUsageLog::query()
            ->forCompany($companyId)
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->sum('estimated_cost_eur');
        $used = round($used, 6);

        if ($budget === null) {
            return [
                'used_eur' => $used,
                'budget_eur' => null,
                'remaining_eur' => null,
                'warning_percent' => $warningPercent,
                'usage_percent' => null,
                'is_warning' => false,
                'is_exceeded' => false,
                'hard_stop_enabled' => $hardStopEnabled,
            ];
        }

        $usagePercent = $budget > 0 ? round(($used / $budget) * 100, 2) : null;
        $remaining = round(max(0, $budget - $used), 6);
        $isExceeded = $used >= $budget;
        $isWarning = ! $isExceeded && $usagePercent !== null && $usagePercent >= $warningPercent;

        return [
            'used_eur' => $used,
            'budget_eur' => $budget,
            'remaining_eur' => $remaining,
            'warning_percent' => $warningPercent,
            'usage_percent' => $usagePercent,
            'is_warning' => $isWarning,
            'is_exceeded' => $isExceeded,
            'hard_stop_enabled' => $hardStopEnabled,
        ];
    }

    public function ensureCanUseAi(Company $company): void
    {
        $usage = $this->getMonthlyUsage((int) $company->id);

        if ($usage['hard_stop_enabled'] && $usage['is_exceeded']) {
            throw new AiBudgetExceededException();
        }
    }

    public function buildWarningMessage(Company $company): ?string
    {
        $usage = $this->getMonthlyUsage((int) $company->id);

        if ($usage['budget_eur'] === null || ! $usage['is_warning']) {
            return null;
        }

        return sprintf(
            'Aviso: utilizou %.2f%% do orcamento mensal de AI (%.2f EUR / %.2f EUR).',
            (float) ($usage['usage_percent'] ?? 0.0),
            (float) $usage['used_eur'],
            (float) $usage['budget_eur']
        );
    }

    /**
     * @return array{start:\Illuminate\Support\Carbon,end:\Illuminate\Support\Carbon}
     */
    private function resolveCurrentMonthRange(): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $now = now($timezone);

        return [
            'start' => $now->copy()->startOfMonth(),
            'end' => $now->copy()->endOfMonth(),
        ];
    }

    private function resolveBudget(Company $company): ?float
    {
        $companyBudget = is_numeric($company->ai_monthly_budget_eur)
            ? (float) $company->ai_monthly_budget_eur
            : null;

        if ($companyBudget !== null && $companyBudget > 0) {
            return round($companyBudget, 2);
        }

        $default = config('ai.monthly_budget_eur');
        if (is_numeric($default) && (float) $default > 0) {
            return round((float) $default, 2);
        }

        return null;
    }

    private function resolveWarningPercent(Company $company): int
    {
        $value = is_numeric($company->ai_budget_warning_percent)
            ? (int) $company->ai_budget_warning_percent
            : (int) config('ai.budget_warning_percent', 80);

        return max(1, min(100, $value));
    }

    private function resolveHardStopEnabled(Company $company): bool
    {
        if ($company->ai_budget_hard_stop_enabled !== null) {
            return (bool) $company->ai_budget_hard_stop_enabled;
        }

        return (bool) config('ai.budget_hard_stop_enabled', true);
    }
}