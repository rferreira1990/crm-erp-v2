<?php

namespace App\Services\Ai;

use App\DTO\Ai\AiResponseData;
use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiUsageLoggerService
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        int $companyId,
        ?int $userId,
        string $source,
        AiResponseData $responseData,
        array $metadata = []
    ): void {
        try {
            AiUsageLog::query()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'source' => trim($source),
                'model' => $responseData->model,
                'input_tokens' => $responseData->inputTokens,
                'output_tokens' => $responseData->outputTokens,
                'total_tokens' => $responseData->totalTokens,
                'estimated_cost_eur' => $responseData->estimatedCostEur,
                'metadata' => $metadata !== [] ? $metadata : null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to persist AI usage log', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'source' => $source,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

